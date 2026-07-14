<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'jane@acme.test', string $companyName = 'Acme Logistics'): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => $email, 'password' => 'SecurePass123'],
            'company' => [
                'name' => $companyName,
                'country' => 'Kenya',
                'city' => 'Nairobi',
                'address' => '123 Port Rd',
                'currency' => 'USD',
                'timezone' => 'Africa/Nairobi',
                'industry' => 'Freight Forwarding',
            ],
        ]);

        return $response->json();
    }

    private function createDepartment(string $token, array $overrides = []): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/departments', array_merge(['name' => 'Operations'], $overrides));

        return $response->json('data.id');
    }

    private function createEmployee(string $token, array $overrides = []): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', array_merge([
                'name' => 'John Kamau',
                'job_title' => 'Warehouse Clerk',
                'hire_date' => now()->subYear()->toDateString(),
            ], $overrides));

        return $response->json('data.id');
    }

    public function test_owner_can_create_a_department(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/departments', ['name' => 'Operations', 'description' => 'Field ops team']);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Operations');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/hr/departments')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Operations']);
    }

    public function test_duplicate_department_name_within_tenant_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $this->createDepartment($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/departments', ['name' => 'Operations'])
            ->assertUnprocessable();
    }

    public function test_owner_can_create_list_update_and_delete_an_employee(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $departmentId = $this->createDepartment($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'department_id' => $departmentId,
                'name' => 'John Kamau',
                'job_title' => 'Warehouse Clerk',
                'employment_type' => 'full_time',
                'hire_date' => now()->subYear()->toDateString(),
            ]);

        $create->assertCreated();
        $employeeId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'active');
        $this->assertNotNull($create->json('data.employee_number'));
        $this->assertStringStartsWith('EMP-', $create->json('data.employee_number'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/hr/employees')
            ->assertOk()
            ->assertJsonFragment(['id' => $employeeId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/hr/employees/{$employeeId}", ['status' => 'on_leave'])
            ->assertOk()
            ->assertJsonPath('data.status', 'on_leave');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/hr/employees/{$employeeId}")
            ->assertStatus(204);

        $this->assertDatabaseHas('audit_logs', ['action' => 'employee.created']);
    }

    public function test_deleting_a_department_nulls_out_its_employees_department_id(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $departmentId = $this->createDepartment($token);
        $employeeId = $this->createEmployee($token, ['department_id' => $departmentId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/hr/departments/{$departmentId}")
            ->assertStatus(204);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/employees/{$employeeId}")
            ->assertOk()
            ->assertJsonPath('data.department_id', null);
    }

    public function test_owner_can_record_and_update_attendance(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);
        $today = now()->toDateString();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/attendance', [
                'employee_id' => $employeeId,
                'date' => $today,
                'status' => 'present',
                'check_in' => now()->setTime(8, 0)->toDateTimeString(),
            ]);

        $create->assertCreated();
        $attendanceId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'present');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/hr/attendance/{$attendanceId}", [
                'check_out' => now()->setTime(17, 0)->toDateTimeString(),
            ])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/attendance?employee_id={$employeeId}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_only_one_attendance_record_per_employee_per_day(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);
        $today = now()->toDateString();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/attendance', ['employee_id' => $employeeId, 'date' => $today])
            ->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/attendance', ['employee_id' => $employeeId, 'date' => $today])
            ->assertUnprocessable();
    }

    public function test_hr_records_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->createDepartment($registrationA['token']);
        $this->createEmployee($registrationA['token']);

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/departments')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/employees')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/attendance')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_hr_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->getJson('/api/v1/hr/departments')->assertForbidden();
        $this->getJson('/api/v1/hr/employees')->assertForbidden();
        $this->getJson('/api/v1/hr/attendance')->assertForbidden();
    }
}
