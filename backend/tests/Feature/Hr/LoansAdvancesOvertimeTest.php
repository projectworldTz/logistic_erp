<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoansAdvancesOvertimeTest extends TestCase
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

    private function createEmployeeWithSalary(string $token, float $basicSalary): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'first_name' => 'John',
                'last_name' => 'Kamau',
                'hire_date' => now()->subYears(2)->toDateString(),
                'payroll_eligible' => true,
                'salary' => $basicSalary,
                'preferred_payment_method' => 'bank_transfer',
                'bank_account_number' => '1234567890',
            ])->json('data.id');
    }

    private function createPeriod(string $token, string $start = '2026-07-01', string $end = '2026-07-31', string $paymentDate = '2026-08-01'): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-periods', [
                'name' => "Period {$start}",
                'period_start' => $start,
                'period_end' => $end,
                'payment_date' => $paymentDate,
            ])->json('data.id');
    }

    public function test_loan_lifecycle_generates_schedule_and_installment_is_deducted_and_settled(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployeeWithSalary($token, 500000);

        $loan = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/loans', [
                'employee_id' => $employeeId,
                'principal_amount' => 120000,
                'interest_rate' => 0,
                'number_of_installments' => 3,
                'start_date' => '2026-07-15',
            ]);
        $loan->assertCreated();
        $loan->assertJsonPath('data.installment_amount', '40000.00');
        $loanId = $loan->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/loans/{$loanId}/submit")->assertOk();

        $approve = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/loans/{$loanId}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('data.status', 'active');
        $approve->assertJsonCount(3, 'data.schedules');
        $approve->assertJsonPath('data.schedules.0.due_date', '2026-07-15T00:00:00.000000Z');

        $periodId = $this->createPeriod($token);
        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');

        $calculate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate");
        $calculate->assertOk();
        $calculate->assertJsonPath('data.total_gross', '500000.00');
        $calculate->assertJsonPath('data.total_deductions', '40000.00');
        $calculate->assertJsonPath('data.total_net', '460000.00');

        // Recalculating must not double-deduct (schedule not yet consumed until finalize).
        $recalculate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate");
        $recalculate->assertJsonPath('data.total_deductions', '40000.00');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/approve")->assertOk();
        $finalize = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/finalize");
        $finalize->assertOk();

        $loanAfter = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/hr/loans/{$loanId}");
        $loanAfter->assertJsonPath('data.status', 'active');
        $loanAfter->assertJsonPath('data.schedules.0.status', 'paid');
        $loanAfter->assertJsonPath('data.schedules.1.status', 'pending');
    }

    public function test_salary_advance_single_installment_completes_on_finalize(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployeeWithSalary($token, 500000);

        $advance = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/salary-advances', [
                'employee_id' => $employeeId,
                'amount' => 50000,
                'number_of_installments' => 1,
                'request_date' => '2026-06-01',
            ]);
        $advance->assertCreated();
        $advanceId = $advance->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/salary-advances/{$advanceId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/salary-advances/{$advanceId}/approve")->assertOk();

        $periodId = $this->createPeriod($token);
        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');

        $calculate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate");
        $calculate->assertJsonPath('data.total_deductions', '50000.00');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/approve")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/finalize")->assertOk();

        $advanceAfter = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/hr/salary-advances/{$advanceId}");
        $advanceAfter->assertJsonPath('data.status', 'completed');
        $advanceAfter->assertJsonPath('data.schedules.0.status', 'paid');
    }

    public function test_approved_overtime_request_is_paid_using_the_configured_multiplier(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployeeWithSalary($token, 520000);

        $overtime = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/overtime-requests', [
                'employee_id' => $employeeId,
                'date' => '2026-07-10',
                'hours' => 4,
            ]);
        $overtime->assertCreated();
        $overtimeId = $overtime->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/overtime-requests/{$overtimeId}/approve")->assertOk();

        $periodId = $this->createPeriod($token);
        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');

        $calculate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate");

        // Hourly rate = 520000 / (26 * 8) = 2500. Overtime = 4h * 2500 * 1.5 multiplier = 15000.
        $calculate->assertJsonPath('data.total_gross', '535000.00');
    }

    public function test_loans_and_advances_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $employeeIdA = $this->createEmployeeWithSalary($registrationA['token'], 500000);
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/hr/loans', [
                'employee_id' => $employeeIdA,
                'principal_amount' => 100000,
                'number_of_installments' => 2,
                'start_date' => '2026-07-01',
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/loans')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/salary-advances')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/overtime-requests')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_loans_approve_permission_cannot_approve(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];
        $employeeId = $this->createEmployeeWithSalary($token, 500000);

        $loanId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/loans', [
                'employee_id' => $employeeId,
                'principal_amount' => 100000,
                'number_of_installments' => 2,
                'start_date' => '2026-07-01',
            ])->json('data.id');
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/loans/{$loanId}/submit")->assertOk();

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->postJson("/api/v1/hr/loans/{$loanId}/approve")->assertForbidden();
    }
}
