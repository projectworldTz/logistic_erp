<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayslipsPaymentsPostingTest extends TestCase
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

    private function createAccount(string $token, string $code, string $type): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/accounts', [
                'code' => $code,
                'name' => "Account {$code}",
                'type' => $type,
            ])->json('data.id');
    }

    /**
     * Sets up a fully mapped payroll settings + statutory rule set +
     * one employee earning 500,000 with a 60,000 (2-installment) loan
     * disbursed, and returns [token, employeeId, loanId].
     */
    private function setupFullScenario(string $token): array
    {
        $employeeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'first_name' => 'John',
                'last_name' => 'Kamau',
                'hire_date' => now()->subYears(2)->toDateString(),
                'payroll_eligible' => true,
                'salary' => 500000,
                'preferred_payment_method' => 'bank_transfer',
                'bank_account_number' => '1234567890',
                'bank_name' => 'Test Bank',
            ])->json('data.id');

        $ruleSetId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/statutory-rule-sets', ['name' => 'Test PAYE', 'country_code' => 'TZ', 'is_default' => true])
            ->json('data.id');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/statutory-rule-sets/{$ruleSetId}/tax-bands", ['lower_bound' => 0, 'upper_bound' => null, 'rate' => 10, 'band_order' => 1])
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/statutory-rule-sets/{$ruleSetId}/contribution-rules", ['code' => 'nssf', 'name' => 'NSSF', 'employee_rate' => 10, 'employer_rate' => 10])
            ->assertCreated();

        $accounts = [
            'salary_expense_account_id' => $this->createAccount($token, 'SAL', 'expense'),
            'allowance_expense_account_id' => $this->createAccount($token, 'ALW', 'expense'),
            'overtime_expense_account_id' => $this->createAccount($token, 'OT', 'expense'),
            'employer_contribution_expense_account_id' => $this->createAccount($token, 'ERC', 'expense'),
            'payroll_payable_account_id' => $this->createAccount($token, 'PAYPBL', 'liability'),
            'tax_payable_account_id' => $this->createAccount($token, 'TAXPBL', 'liability'),
            'statutory_contributions_payable_account_id' => $this->createAccount($token, 'STATPBL', 'liability'),
            'loan_receivable_account_id' => $this->createAccount($token, 'LOANRCV', 'asset'),
            'advance_receivable_account_id' => $this->createAccount($token, 'ADVRCV', 'asset'),
            'other_deductions_payable_account_id' => $this->createAccount($token, 'OTHPBL', 'liability'),
            'bank_cash_account_id' => $this->createAccount($token, 'BANK', 'asset'),
            'statutory_rule_set_id' => $ruleSetId,
        ];
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/hr/payroll-settings', $accounts)->assertOk();

        $loanId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/loans', [
                'employee_id' => $employeeId,
                'principal_amount' => 60000,
                'interest_rate' => 0,
                'number_of_installments' => 2,
                'start_date' => '2026-07-15',
            ])->json('data.id');
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/loans/{$loanId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/loans/{$loanId}/approve")->assertOk();

        return [$employeeId, $loanId, $accounts];
    }

    private function runFullPayrollCycle(string $token): array
    {
        [$employeeId] = $this->setupFullScenario($token);

        $periodId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-periods', [
                'name' => 'July 2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'payment_date' => '2026-08-01',
            ])->json('data.id');

        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/approve")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/finalize")->assertOk();

        return [$runId, $employeeId];
    }

    public function test_payslip_is_generated_on_finalize_with_correct_net_and_ytd(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        [$runId, $employeeId] = $this->runFullPayrollCycle($token);

        // Gross 500,000; tax 10% = 50,000; NSSF employee 10% = 50,000; loan installment 30,000.
        // Total deductions = 130,000. Net = 370,000.
        $payslips = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/payslips?employee_id={$employeeId}");
        $payslips->assertOk();
        $payslips->assertJsonCount(1, 'data');
        $payslips->assertJsonPath('data.0.gross_pay', '500000.00');
        $payslips->assertJsonPath('data.0.total_deductions', '130000.00');
        $payslips->assertJsonPath('data.0.net_pay', '370000.00');
        $payslips->assertJsonPath('data.0.ytd_net', '370000.00');

        $payslipId = $payslips->json('data.0.id');
        $pdf = $this->withHeader('Authorization', "Bearer {$token}")->get("/api/v1/hr/payslips/{$payslipId}/pdf");
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
    }

    public function test_payslip_verification_code_is_publicly_verifiable(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        [$runId, $employeeId] = $this->runFullPayrollCycle($token);

        $payslip = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/payslips?employee_id={$employeeId}")->json('data.0');

        $code = \App\Models\Payslip::query()->findOrFail($payslip['id'])->verification_code;

        $verify = $this->getJson("/api/v1/public/verify/payslip/{$code}");
        $verify->assertOk();
        $verify->assertJsonPath('data.net_pay', '370000.00');

        $this->getJson('/api/v1/public/verify/payslip/not-a-real-code')->assertNotFound();
    }

    public function test_salary_payment_batch_is_generated_with_snapshotted_bank_details(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        [$runId] = $this->runFullPayrollCycle($token);

        $batch = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/salary-payments");
        $batch->assertOk();
        $batch->assertJsonPath('data.total_amount', '370000.00');
        $batch->assertJsonCount(1, 'data.payments');
        $batch->assertJsonPath('data.payments.0.amount', '370000.00');
        $batch->assertJsonPath('data.payments.0.bank_account_number', '1234567890');

        $paymentId = $batch->json('data.payments.0.id');
        $mark = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/hr/salary-payments/{$paymentId}", ['status' => 'paid', 'reference' => 'TXN-001']);
        $mark->assertOk();
        $mark->assertJsonPath('data.status', 'paid');

        $batchAfter = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/salary-payment-batches/{$batch->json('data.id')}");
        $batchAfter->assertJsonPath('data.status', 'completed');

        $csv = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/hr/salary-payment-batches/{$batch->json('data.id')}/export");
        $csv->assertOk();
    }

    public function test_posting_to_accounting_creates_a_balanced_journal_entry(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        [$runId] = $this->runFullPayrollCycle($token);

        $post = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/post-to-accounting");
        $post->assertOk();
        $post->assertJsonPath('data.journal_entry_id', fn ($id) => $id !== null);

        $entryId = $post->json('data.journal_entry_id');
        $entry = \App\Models\JournalEntry::with('lines')->findOrFail($entryId);

        $totalDebit = $entry->lines->sum('debit');
        $totalCredit = $entry->lines->sum('credit');
        $this->assertEquals(0, bccomp((string) $totalDebit, (string) $totalCredit, 2), 'Debits and credits must balance exactly.');
        // Debit: 500,000 salary + 50,000 employer NSSF = 550,000.
        $this->assertEquals('550000.00', number_format((float) $totalDebit, 2, '.', ''));
        $this->assertEquals('posted', $entry->status->value);

        // Posting twice must be rejected — the run is already linked to a journal entry.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/post-to-accounting")
            ->assertStatus(409);
    }

    public function test_posting_fails_clearly_when_an_account_mapping_is_missing(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployeeOnly($token);

        $periodId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-periods', [
                'name' => 'August 2026', 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'payment_date' => '2026-09-01',
            ])->json('data.id');
        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs", [])->json('data.id');
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/approve")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/hr/payroll-runs/{$runId}/finalize")->assertOk();

        // No GL accounts mapped at all yet.
        $post = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/post-to-accounting");
        $post->assertStatus(422);
        $post->assertSee('Salary Expense Account');
    }

    private function createEmployeeOnly(string $token): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'first_name' => 'Alice',
                'last_name' => 'Wanjiru',
                'hire_date' => now()->subYear()->toDateString(),
                'payroll_eligible' => true,
                'salary' => 300000,
                'preferred_payment_method' => 'bank_transfer',
                'bank_account_number' => '999',
            ])->json('data.id');
    }

    public function test_only_own_payslip_is_visible_to_a_self_service_employee(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];
        [$runId, $employeeId] = $this->runFullPayrollCycle($token);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $selfServiceUser = User::factory()->create(['tenant_id' => $tenantId]);
        \App\Models\Employee::query()->where('id', $employeeId)->update(['user_id' => $selfServiceUser->id]);

        app(TenantContext::class)->clear();
        Sanctum::actingAs($selfServiceUser, ['*']);
        app(TenantContext::class)->set($tenantId);

        $ownList = $this->getJson('/api/v1/hr/payslips');
        $ownList->assertOk();
        $ownList->assertJsonCount(1, 'data');

        $payslipId = $ownList->json('data.0.id');
        $this->getJson("/api/v1/hr/payslips/{$payslipId}")->assertOk();
    }

    public function test_payroll_run_posting_and_payslips_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        [$runId] = $this->runFullPayrollCycle($registrationA['token']);
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/salary-payments")->assertOk();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/payslips')->assertOk()->assertJsonCount(0, 'data');
    }
}
