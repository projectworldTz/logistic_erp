<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceDisciplinaryAssetsExitTest extends TestCase
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

    private function createEmployee(string $token, array $overrides = []): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', array_merge([
                'first_name' => 'John',
                'last_name' => 'Kamau',
                'hire_date' => now()->subYears(2)->toDateString(),
                'payroll_eligible' => true,
                'salary' => 500000,
            ], $overrides))
            ->json('data.id');
    }

    public function test_performance_review_lifecycle_submit_and_self_service_acknowledge(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $employeeUser = User::factory()->create(['tenant_id' => $tenantId]);
        $employeeId = $this->createEmployee($token, ['user_id' => $employeeUser->id]);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/performance-reviews', [
                'employee_id' => $employeeId,
                'review_period_start' => '2026-01-01',
                'review_period_end' => '2026-06-30',
                'review_date' => '2026-07-01',
                'overall_rating' => 4.5,
                'kpi_scores' => ['on_time_delivery' => 90, 'customer_satisfaction' => 85],
            ]);
        $create->assertCreated();
        $reviewId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'draft');

        $submit = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/performance-reviews/{$reviewId}/submit");
        $submit->assertOk();
        $submit->assertJsonPath('data.status', 'submitted');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($employeeUser, ['*']);
        app(TenantContext::class)->set($tenantId);

        $ack = $this->postJson("/api/v1/hr/performance-reviews/{$reviewId}/acknowledge", ['employee_comments' => 'Agreed, thank you.']);
        $ack->assertOk();
        $ack->assertJsonPath('data.status', 'acknowledged');
        $ack->assertJsonPath('data.employee_comments', 'Agreed, thank you.');
    }

    public function test_only_own_performance_reviews_visible_without_view_all_permission(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $employeeUserA = User::factory()->create(['tenant_id' => $tenantId]);
        $employeeIdA = $this->createEmployee($token, ['user_id' => $employeeUserA->id, 'first_name' => 'Alice']);
        $employeeIdB = $this->createEmployee($token, ['first_name' => 'Bob']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/performance-reviews', [
                'employee_id' => $employeeIdA, 'review_period_start' => '2026-01-01',
                'review_period_end' => '2026-06-30', 'review_date' => '2026-07-01',
            ])->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/performance-reviews', [
                'employee_id' => $employeeIdB, 'review_period_start' => '2026-01-01',
                'review_period_end' => '2026-06-30', 'review_date' => '2026-07-01',
            ])->assertCreated();

        app(TenantContext::class)->clear();
        Sanctum::actingAs($employeeUserA, ['*']);
        app(TenantContext::class)->set($tenantId);

        $list = $this->getJson('/api/v1/hr/performance-reviews');
        $list->assertOk();
        $list->assertJsonCount(1, 'data');
        $list->assertJsonPath('data.0.employee_id', $employeeIdA);
    }

    public function test_disciplinary_record_issue_acknowledge_resolve_lifecycle(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/disciplinary-records', [
                'employee_id' => $employeeId,
                'incident_date' => '2026-07-10',
                'category' => 'attendance',
                'severity' => 'verbal_warning',
                'description' => 'Repeated late arrivals.',
            ]);
        $create->assertCreated();
        $create->assertJsonPath('data.status', 'issued');
        $recordId = $create->json('data.id');

        $ack = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/disciplinary-records/{$recordId}/acknowledge", ['employee_response' => 'Noted.']);
        $ack->assertOk();
        $ack->assertJsonPath('data.status', 'acknowledged');

        $resolve = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/disciplinary-records/{$recordId}/resolve");
        $resolve->assertOk();
        $resolve->assertJsonPath('data.status', 'resolved');
    }

    public function test_employee_asset_assignment_and_return(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employee-assets', [
                'employee_id' => $employeeId,
                'asset_type' => 'laptop',
                'asset_name' => 'Dell Latitude 5420',
                'serial_number' => 'SN-001',
                'assigned_date' => '2026-01-01',
            ]);
        $create->assertCreated();
        $create->assertJsonPath('data.status', 'assigned');
        $assetId = $create->json('data.id');

        $return = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/employee-assets/{$assetId}/return", [
                'return_date' => '2026-07-20',
                'condition_at_return' => 'Good',
                'status' => 'returned',
            ]);
        $return->assertOk();
        $return->assertJsonPath('data.status', 'returned');
    }

    public function test_exit_record_computes_settlement_and_completes(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        // Give the employee a leave balance and an active loan to settle.
        $leaveTypeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-types', ['name' => 'Annual Leave'])->json('data.id');
        \App\Models\LeaveBalance::create([
            'tenant_id' => $registration['user']['tenant_id'], 'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId, 'year' => now()->year,
            'entitled_days' => 21, 'used_days' => 5, 'carried_forward_days' => 0,
        ]);

        $loanId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/loans', [
                'employee_id' => $employeeId, 'principal_amount' => 52000,
                'interest_rate' => 0, 'number_of_installments' => 2, 'start_date' => now()->toDateString(),
            ])->json('data.id');
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/loans/{$loanId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/loans/{$loanId}/approve")->assertOk();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/exit-records', [
                'employee_id' => $employeeId,
                'exit_type' => 'resignation',
                'notice_date' => now()->toDateString(),
                'last_working_date' => now()->addDays(30)->toDateString(),
                'reason' => 'Better opportunity.',
            ]);
        $create->assertCreated();
        $exitId = $create->json('data.id');

        // 16 unused days (21 - 5) * (500000/26) daily rate = 307,692.30..., minus 52,000 outstanding loan.
        $create->assertJsonPath('data.unused_leave_days', '16.00');
        $create->assertJsonPath('data.outstanding_loan_balance', '52000.00');

        $clearance = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/hr/exit-records/{$exitId}", ['assets_cleared' => true, 'handover_completed' => true]);
        $clearance->assertOk();
        $clearance->assertJsonPath('data.status', 'cleared');

        $complete = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/exit-records/{$exitId}/complete");
        $complete->assertOk();
        $complete->assertJsonPath('data.status', 'completed');

        $employee = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/employees/{$employeeId}");
        $employee->assertJsonPath('data.status', 'terminated');
    }

    public function test_a_second_exit_record_for_the_same_employee_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/exit-records', [
                'employee_id' => $employeeId, 'exit_type' => 'resignation',
                'notice_date' => now()->toDateString(), 'last_working_date' => now()->addDays(30)->toDateString(),
            ])->assertCreated();

        $duplicate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/exit-records', [
                'employee_id' => $employeeId, 'exit_type' => 'resignation',
                'notice_date' => now()->toDateString(), 'last_working_date' => now()->addDays(30)->toDateString(),
            ]);
        $duplicate->assertUnprocessable();
    }

    public function test_hr_phase7_records_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $employeeIdA = $this->createEmployee($registrationA['token']);
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/hr/employee-assets', [
                'employee_id' => $employeeIdA, 'asset_type' => 'laptop', 'asset_name' => 'Test Laptop', 'assigned_date' => now()->toDateString(),
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/employee-assets')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/disciplinary-records')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/exit-records')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_disciplinary_manage_permission_cannot_issue_a_record(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];
        $employeeId = $this->createEmployee($token);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->postJson('/api/v1/hr/disciplinary-records', [
            'employee_id' => $employeeId, 'incident_date' => now()->toDateString(),
            'category' => 'conduct', 'severity' => 'verbal_warning', 'description' => 'x',
        ])->assertForbidden();
    }
}
