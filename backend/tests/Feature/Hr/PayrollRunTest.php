<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollRunTest extends TestCase
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

    private function createEmployeeWithSalary(string $token, float $basicSalary, array $overrides = []): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', array_merge([
                'first_name' => 'John',
                'last_name' => 'Kamau',
                'hire_date' => now()->subYears(2)->toDateString(),
                'payroll_eligible' => true,
                'salary' => $basicSalary,
                'preferred_payment_method' => 'bank_transfer',
                'bank_account_number' => '1234567890',
            ], $overrides))
            ->json('data.id');
    }

    private function setupStatutoryRuleSet(string $token): int
    {
        $ruleSetId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/statutory-rule-sets', [
                'name' => 'Test PAYE',
                'country_code' => 'TZ',
                'is_default' => true,
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/statutory-rule-sets/{$ruleSetId}/tax-bands", [
                'lower_bound' => 0,
                'upper_bound' => null,
                'rate' => 10,
                'band_order' => 1,
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/statutory-rule-sets/{$ruleSetId}/contribution-rules", [
                'code' => 'nssf',
                'name' => 'NSSF',
                'employee_rate' => 10,
                'employer_rate' => 10,
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/hr/payroll-settings', ['statutory_rule_set_id' => $ruleSetId])
            ->assertOk();

        return $ruleSetId;
    }

    private function createPeriod(string $token, array $overrides = []): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-periods', array_merge([
                'name' => 'July 2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'payment_date' => '2026-08-01',
            ], $overrides))
            ->json('data.id');
    }

    public function test_payroll_run_calculates_earnings_deductions_and_net_pay_correctly(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $employeeId = $this->createEmployeeWithSalary($token, 1000000);
        $this->setupStatutoryRuleSet($token);

        $componentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-components', [
                'code' => 'housing_allowance',
                'name' => 'Housing Allowance',
                'type' => 'earning',
                'calculation_method' => 'fixed',
                'amount' => 200000,
                'is_taxable' => true,
                'is_pensionable' => false,
                'effective_date' => '2026-01-01',
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employee-payroll-components', [
                'employee_id' => $employeeId,
                'payroll_component_id' => $componentId,
                'effective_date' => '2026-01-01',
            ])->assertCreated();

        $periodId = $this->createPeriod($token);

        $run = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", []);
        $run->assertCreated();
        $runId = $run->json('data.id');

        $calculate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate");

        $calculate->assertOk();
        $calculate->assertJsonPath('data.status', 'calculated');
        // Gross = 1,000,000 basic + 200,000 housing = 1,200,000
        $calculate->assertJsonPath('data.total_gross', '1200000.00');
        // Tax = 10% of taxable (1,200,000) = 120,000. NSSF employee = 10% of pensionable (1,000,000, housing excluded) = 100,000.
        $calculate->assertJsonPath('data.total_deductions', '220000.00');
        $calculate->assertJsonPath('data.total_net', '980000.00');
        // NSSF employer = 10% of 1,000,000 = 100,000.
        $calculate->assertJsonPath('data.total_employer_contributions', '100000.00');
        $calculate->assertJsonPath('data.total_employer_cost', '1300000.00');

        $show = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/hr/payroll-runs/{$runId}");
        $show->assertOk();
        $show->assertJsonCount(1, 'data.run_employees');
        $show->assertJsonPath('data.run_employees.0.status', 'included');
        $show->assertJsonPath('data.run_employees.0.net_pay', '980000.00');
    }

    public function test_payroll_run_blocks_submission_while_an_employee_has_an_exception(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        // Salary of 0 (and no active contract) triggers a blocking precondition: no basic salary set.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'first_name' => 'John',
                'last_name' => 'Kamau',
                'hire_date' => now()->subYears(2)->toDateString(),
                'payroll_eligible' => true,
                'salary' => 0,
            ])->assertCreated();
        $periodId = $this->createPeriod($token);
        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate")->assertOk();

        $submit = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/submit");

        $submit->assertStatus(422);
    }

    public function test_payroll_run_full_lifecycle_submit_approve_finalize_locks_the_period(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->createEmployeeWithSalary($token, 500000);
        $periodId = $this->createPeriod($token);
        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate")->assertOk();

        $submit = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/submit");
        $submit->assertOk();
        $submit->assertJsonPath('data.status', 'pending_approval');

        $approve = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('data.status', 'approved');

        $finalize = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/finalize");
        $finalize->assertOk();
        $finalize->assertJsonPath('data.status', 'finalized');

        $period = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/payroll-periods/{$periodId}");
        $period->assertJsonPath('data.is_locked', true);
    }

    public function test_payroll_run_rejection_requires_a_reason(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->createEmployeeWithSalary($token, 500000);
        $periodId = $this->createPeriod($token);
        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/submit")->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/reject", [])
            ->assertUnprocessable();

        $reject = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/reject", ['reason' => 'Salary figures look wrong']);
        $reject->assertOk();
        $reject->assertJsonPath('data.status', 'rejected');
    }

    public function test_payroll_runs_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->createEmployeeWithSalary($registrationA['token'], 500000);
        $periodId = $this->createPeriod($registrationA['token']);
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/payroll-periods')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/payroll-runs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_creating_a_payroll_period_with_duplicate_dates_returns_a_validation_error(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->createPeriod($token);

        $duplicate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-periods', [
                'name' => 'A Different Name',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'payment_date' => '2026-08-05',
            ]);

        $duplicate->assertUnprocessable();
        $duplicate->assertJsonValidationErrors('period_start');
    }

    public function test_user_without_payroll_runs_permission_cannot_calculate(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        $this->createEmployeeWithSalary($token, 500000);
        $periodId = $this->createPeriod($token);
        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $hrOfficer = User::factory()->create(['tenant_id' => $tenantId]);
        $hrOfficer->assignRole('HR Officer');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($hrOfficer, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate")->assertForbidden();
    }
}
