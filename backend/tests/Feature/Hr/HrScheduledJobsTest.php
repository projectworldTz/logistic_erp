<?php

namespace Tests\Feature\Hr;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\UserNotification;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrScheduledJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => 'jane@acme.test', 'password' => 'SecurePass123'],
            'company' => [
                'name' => 'Acme Logistics', 'country' => 'Kenya', 'city' => 'Nairobi', 'address' => '123 Port Rd',
                'currency' => 'USD', 'timezone' => 'Africa/Nairobi', 'industry' => 'Freight Forwarding',
            ],
        ]);

        return $response->json();
    }

    public function test_hr_daily_checks_notifies_about_an_expiring_contract(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $employeeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', ['first_name' => 'John', 'last_name' => 'Kamau', 'hire_date' => now()->subYears(2)->toDateString()])
            ->json('data.id');

        $contract = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/contracts', [
                'employee_id' => $employeeId,
                'employment_type' => 'permanent',
                'effective_date' => now()->subYear()->toDateString(),
                'expiry_date' => now()->addDays(10)->toDateString(),
                'basic_salary' => 500000,
                'pay_frequency' => 'monthly',
            ]);
        $contract->assertCreated();
        $contractId = $contract->json('data.id');
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/contracts/{$contractId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/contracts/{$contractId}/approve")->assertOk();

        $this->artisan('hr:daily-checks')->assertExitCode(0);

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $registration['user']['tenant_id'],
            'type' => 'employee_contract.expiring_soon',
        ]);
    }

    public function test_leave_accrual_grants_one_twelfth_of_annual_days_to_eligible_employees(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        $employeeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', ['first_name' => 'Alice', 'last_name' => 'Njoroge', 'hire_date' => now()->subYears(2)->toDateString()])
            ->json('data.id');

        $leaveTypeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-types', ['name' => 'Annual Leave', 'default_annual_days' => 24, 'accrual_rule' => 'monthly'])
            ->json('data.id');

        $this->artisan('hr:accrue-leave')->assertExitCode(0);

        $balance = LeaveBalance::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', now()->year)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(2.0, (float) $balance->entitled_days);

        // Running it twice in the same month should accrue again (idempotency is a scheduling concern, not this service's).
        $this->artisan('hr:accrue-leave')->assertExitCode(0);
        $this->assertEquals(4.0, (float) $balance->fresh()->entitled_days);
    }
}
