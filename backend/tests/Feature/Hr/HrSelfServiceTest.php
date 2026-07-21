<?php

namespace Tests\Feature\Hr;

use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HrSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'jane@acme.test'): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => $email, 'password' => 'SecurePass123'],
            'company' => [
                'name' => 'Acme Logistics',
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

    private function actingAsSelfServiceEmployee(int $tenantId, int $employeeId): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $user->assignRole('Employee');
        Employee::query()->where('id', $employeeId)->update(['user_id' => $user->id]);

        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['*']);
        app(TenantContext::class)->set($tenantId);

        return $user;
    }

    public function test_self_service_employee_can_reach_their_own_records_but_not_the_broad_hr_endpoints(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        $employeeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'first_name' => 'Alice',
                'last_name' => 'Wanjiru',
                'hire_date' => now()->subYear()->toDateString(),
                'payroll_eligible' => true,
                'salary' => 300000,
            ])->json('data.id');

        $leaveTypeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-types', ['name' => 'Annual Leave', 'default_annual_days' => 21])
            ->json('data.id');

        $this->actingAsSelfServiceEmployee($tenantId, $employeeId);

        // Own profile, attendance, leave balances/requests, assets all reachable.
        $this->getJson('/api/v1/hr/my/profile')->assertOk()->assertJsonPath('data.id', $employeeId);
        $this->getJson('/api/v1/hr/my/attendance')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/my/leave-types')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/hr/my/leave-balances')->assertOk();
        $this->getJson('/api/v1/hr/my/leave-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/my/assets')->assertOk()->assertJsonCount(0, 'data');

        // Create and then cancel a leave request for oneself.
        $create = $this->postJson('/api/v1/hr/my/leave-requests', [
            'leave_type_id' => $leaveTypeId,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'reason' => 'Family trip',
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.employee_id', $employeeId);
        $leaveRequestId = $create->json('data.id');

        $this->getJson('/api/v1/hr/my/leave-requests')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/hr/my/leave-requests/{$leaveRequestId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // No hr.* permissions at all — the broad staff endpoints stay forbidden.
        $this->getJson('/api/v1/hr/employees')->assertForbidden();
        $this->getJson('/api/v1/hr/leave-requests')->assertForbidden();
        $this->getJson('/api/v1/hr/attendance')->assertForbidden();
    }

    public function test_self_service_employee_cannot_cancel_another_employees_leave_request(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        $ownerHeader = ['Authorization' => "Bearer {$token}"];
        $employeeIdA = $this->withHeaders($ownerHeader)->postJson('/api/v1/hr/employees', [
            'first_name' => 'Alice', 'last_name' => 'A', 'hire_date' => now()->subYear()->toDateString(), 'payroll_eligible' => true, 'salary' => 300000,
        ])->json('data.id');
        $employeeIdB = $this->withHeaders($ownerHeader)->postJson('/api/v1/hr/employees', [
            'first_name' => 'Brian', 'last_name' => 'B', 'hire_date' => now()->subYear()->toDateString(), 'payroll_eligible' => true, 'salary' => 300000,
        ])->json('data.id');
        $leaveTypeId = $this->withHeaders($ownerHeader)->postJson('/api/v1/hr/leave-types', ['name' => 'Annual Leave'])->json('data.id');

        $leaveRequestOfB = $this->withHeaders($ownerHeader)->postJson('/api/v1/hr/leave-requests', [
            'employee_id' => $employeeIdB,
            'leave_type_id' => $leaveTypeId,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ])->json('data.id');

        $this->actingAsSelfServiceEmployee($tenantId, $employeeIdA);

        $this->postJson("/api/v1/hr/my/leave-requests/{$leaveRequestOfB}/cancel")->assertForbidden();
    }

    public function test_my_hr_endpoints_404_cleanly_for_a_user_with_no_linked_employee_record(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $user->assignRole('Employee');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->getJson('/api/v1/hr/my/profile')->assertNotFound();
    }
}
