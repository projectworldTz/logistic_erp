<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollStructureTest extends TestCase
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
                'hire_date' => now()->subYear()->toDateString(),
            ], $overrides))
            ->json('data.id');
    }

    private function createAccount(string $token, array $overrides = []): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/accounts', array_merge([
                'code' => (string) random_int(10000, 99999),
                'name' => 'Salaries Expense',
                'type' => 'expense',
            ], $overrides))
            ->json('data.id');
    }

    public function test_payroll_component_can_be_created_and_assigned_to_an_employee(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-components', [
                'code' => 'housing_allowance',
                'name' => 'Housing Allowance',
                'type' => 'earning',
                'calculation_method' => 'fixed',
                'amount' => 150000,
                'is_taxable' => true,
                'is_pensionable' => false,
                'effective_date' => now()->toDateString(),
            ]);

        $create->assertCreated();
        $componentId = $create->json('data.id');

        $assign = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employee-payroll-components', [
                'employee_id' => $employeeId,
                'payroll_component_id' => $componentId,
                'effective_date' => now()->toDateString(),
            ]);

        $assign->assertCreated();
        $assign->assertJsonPath('data.payroll_component.code', 'housing_allowance');
    }

    public function test_percentage_component_requires_a_percentage_base(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-components', [
                'code' => 'overtime_pay',
                'name' => 'Overtime Pay',
                'type' => 'earning',
                'calculation_method' => 'percentage',
                'percentage' => 10,
                'effective_date' => now()->toDateString(),
            ]);

        $create->assertUnprocessable();
        $create->assertJsonValidationErrors('percentage_base');
    }

    public function test_statutory_rule_set_can_be_created_with_tax_bands_and_contribution_rules(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $ruleSetId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/statutory-rule-sets', [
                'name' => 'Tanzania PAYE (example)',
                'country_code' => 'TZ',
                'is_default' => true,
            ])->json('data.id');

        $band = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/statutory-rule-sets/{$ruleSetId}/tax-bands", [
                'lower_bound' => 0,
                'upper_bound' => 270000,
                'rate' => 0,
                'band_order' => 1,
            ]);
        $band->assertCreated();

        $rule = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/statutory-rule-sets/{$ruleSetId}/contribution-rules", [
                'code' => 'nssf',
                'name' => 'NSSF',
                'employee_rate' => 10,
                'employer_rate' => 10,
            ]);
        $rule->assertCreated();

        $show = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/statutory-rule-sets/{$ruleSetId}");
        $show->assertOk();
        $show->assertJsonCount(1, 'data.tax_bands');
        $show->assertJsonCount(1, 'data.contribution_rules');
    }

    public function test_payroll_settings_can_be_updated_with_account_mappings(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $accountId = $this->createAccount($token);

        $show = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/hr/payroll-settings');
        $show->assertOk();
        $show->assertJsonPath('data.overtime_multiplier', '1.50');

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/hr/payroll-settings', [
                'salary_expense_account_id' => $accountId,
                'overtime_multiplier' => 2,
            ]);

        $update->assertOk();
        $update->assertJsonPath('data.salary_expense_account_id', $accountId);
        $update->assertJsonPath('data.overtime_multiplier', '2.00');
    }

    public function test_payroll_structure_is_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/hr/payroll-components', [
                'code' => 'basic_salary',
                'name' => 'Basic Salary',
                'type' => 'earning',
                'calculation_method' => 'fixed',
                'amount' => 500000,
                'effective_date' => now()->toDateString(),
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/payroll-components')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/statutory-rule-sets')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_payroll_settings_permission_cannot_update_settings(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $hrOfficer = User::factory()->create(['tenant_id' => $tenantId]);
        $hrOfficer->assignRole('HR Officer');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($hrOfficer, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->getJson('/api/v1/hr/payroll-settings')->assertForbidden();
        $this->getJson('/api/v1/hr/payroll-components')->assertOk();
    }
}
