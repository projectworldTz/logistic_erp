<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\AssetType;
use App\Enums\ContractStatus;
use App\Enums\EmployeeAssetStatus;
use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Enums\PayrollCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Enums\PerformanceReviewStatus;
use App\Models\Account;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeContract;
use App\Models\EmployeeLoan;
use App\Models\EmployeePayrollComponent;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSettings;
use App\Models\PerformanceReview;
use App\Models\PublicHoliday;
use App\Models\StatutoryContributionRule;
use App\Models\StatutoryRuleSet;
use App\Models\StatutoryTaxBand;
use App\Models\User;
use App\Services\Hr\LeaveRequestService;
use App\Services\Payroll\LoanService;
use App\Services\Payroll\PayrollApprovalService;
use App\Services\Payroll\PayrollCalculationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Populates realistic HR & Payroll data — departments, designations,
 * employees, contracts, attendance, leave, payroll components, a
 * statutory rule set, payroll settings, one fully calculated-and-finalized
 * payroll run (real payslips via the actual calculation engine, not
 * hand-typed numbers), loans, assets and performance reviews — for the
 * "Trace Clearing & Forwarding Ltd" tenant, identified by its owner's
 * email, mirroring TraceClearingForwardingDataSeeder's own convention.
 * Not part of the standard install seed list — run explicitly:
 *   php artisan db:seed --class=TraceHrPayrollDataSeeder --force
 */
class TraceHrPayrollDataSeeder extends Seeder
{
    private const OWNER_EMAIL = 'trace@gmail.com';

    public function run(): void
    {
        $owner = User::where('email', self::OWNER_EMAIL)->firstOrFail();
        $tenantId = $owner->tenant_id;
        $branchId = $owner->branch_id;

        // A seeder runs outside the normal HTTP request lifecycle, so
        // neither the 'tenant' middleware (which sets TenantContext) nor
        // Sanctum's auth flow (which sets Spatie's team-scoped permission
        // context) have run — both must be set explicitly, or every
        // global-scope query and every permission check (e.g. the
        // legacy-approval-fallback inside PayrollApprovalService) silently
        // resolves against the wrong/no tenant.
        app(TenantContext::class)->set($tenantId);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        // Wrapped in a transaction so a failure partway through (e.g. a
        // missing permission during seedContracts()) rolls back everything
        // instead of leaving orphaned departments/employees behind for the
        // next attempt to collide with.
        DB::transaction(function () use ($tenantId, $branchId, $owner) {
            $departments = $this->seedDepartments($tenantId, $branchId);
            $designations = $this->seedDesignations($tenantId);
            $employees = $this->seedEmployees($tenantId, $departments, $designations, $branchId);
            $this->seedContracts($tenantId, $employees);
            $this->seedPublicHolidays($tenantId);
            $this->seedAttendance($tenantId, $employees);
            $leaveTypes = $this->seedLeaveTypesAndBalances($tenantId, $employees);
            $this->seedLeaveRequests($tenantId, $employees, $leaveTypes, $owner->id);
            $ruleSet = $this->seedStatutoryRuleSet($tenantId);
            $components = $this->seedPayrollComponents($tenantId);
            $this->assignPayrollComponents($tenantId, $employees, $components);
            $accounts = $this->seedPayrollAccounts($tenantId);
            $this->seedPayrollSettings($tenantId, $ruleSet, $accounts);
            $this->runFullPayrollCycle($tenantId, $owner);
            $this->seedLoans($tenantId, $employees, $owner->id);
            $this->seedAssets($tenantId, $employees, $owner->id);
            $this->seedPerformanceReviews($tenantId, $employees, $owner->id);
        });
    }

    private function seedDepartments(int $tenantId, ?int $branchId): array
    {
        $names = ['Operations', 'Clearing & Customs', 'Finance & Accounts', 'Warehouse & Fleet', 'Sales & Client Services', 'Administration'];

        return collect($names)
            ->mapWithKeys(fn (string $name) => [$name => Department::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'name' => $name,
                'description' => "{$name} department.",
            ])])
            ->all();
    }

    private function seedDesignations(int $tenantId): array
    {
        $rows = [
            ['name' => 'Operations Manager', 'category' => 'management'],
            ['name' => 'Clearing Officer', 'category' => 'clearing_and_customs'],
            ['name' => 'Customs Documentation Officer', 'category' => 'clearing_and_customs'],
            ['name' => 'Forwarding Coordinator', 'category' => 'forwarding_and_logistics'],
            ['name' => 'Accountant', 'category' => 'finance_and_accounts'],
            ['name' => 'Warehouse Supervisor', 'category' => 'warehouse_and_cargo'],
            ['name' => 'Truck Driver', 'category' => 'transport_and_fleet'],
            ['name' => 'Client Relations Officer', 'category' => 'sales_and_crm'],
            ['name' => 'Office Administrator', 'category' => 'administration_and_support'],
        ];

        return collect($rows)
            ->mapWithKeys(fn (array $row) => [$row['name'] => Designation::create($row + ['tenant_id' => $tenantId, 'is_active' => true])])
            ->all();
    }

    private function seedEmployees(int $tenantId, array $departments, array $designations, ?int $branchId): array
    {
        $rows = [
            ['first_name' => 'Emmanuel', 'last_name' => 'Mrema', 'gender' => 'male', 'department' => 'Operations', 'designation' => 'Operations Manager', 'salary' => 2800000, 'hire_years_ago' => 4, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Grace', 'last_name' => 'Mushi', 'gender' => 'female', 'department' => 'Clearing & Customs', 'designation' => 'Clearing Officer', 'salary' => 1500000, 'hire_years_ago' => 3, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Juma', 'last_name' => 'Ally', 'gender' => 'male', 'department' => 'Clearing & Customs', 'designation' => 'Clearing Officer', 'salary' => 1450000, 'hire_years_ago' => 2, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Neema', 'last_name' => 'Kimaro', 'gender' => 'female', 'department' => 'Clearing & Customs', 'designation' => 'Customs Documentation Officer', 'salary' => 1300000, 'hire_years_ago' => 1, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Daudi', 'last_name' => 'Mwakalinga', 'gender' => 'male', 'department' => 'Operations', 'designation' => 'Forwarding Coordinator', 'salary' => 1600000, 'hire_years_ago' => 2, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Rehema', 'last_name' => 'Kessy', 'gender' => 'female', 'department' => 'Finance & Accounts', 'designation' => 'Accountant', 'salary' => 1700000, 'hire_years_ago' => 3, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Ibrahim', 'last_name' => 'Chuma', 'gender' => 'male', 'department' => 'Warehouse & Fleet', 'designation' => 'Warehouse Supervisor', 'salary' => 1350000, 'hire_years_ago' => 2, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Zainabu', 'last_name' => 'Hamisi', 'gender' => 'female', 'department' => 'Sales & Client Services', 'designation' => 'Client Relations Officer', 'salary' => 1250000, 'hire_years_ago' => 1, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Peter', 'last_name' => 'Massawe', 'gender' => 'male', 'department' => 'Warehouse & Fleet', 'designation' => 'Truck Driver', 'salary' => 900000, 'hire_years_ago' => 2, 'employment_type' => EmploymentType::Driver->value, 'status' => EmployeeStatus::Active->value, 'payment_method' => 'mobile_money'],
            ['first_name' => 'Salma', 'last_name' => 'Rajabu', 'gender' => 'female', 'department' => 'Administration', 'designation' => 'Office Administrator', 'salary' => 950000, 'hire_years_ago' => 1, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Active->value],
            ['first_name' => 'Godfrey', 'last_name' => 'Mollel', 'gender' => 'male', 'department' => 'Warehouse & Fleet', 'designation' => 'Truck Driver', 'salary' => 850000, 'hire_years_ago' => 0, 'employment_type' => EmploymentType::Casual->value, 'status' => EmployeeStatus::Probation->value, 'payment_method' => 'mobile_money'],
            ['first_name' => 'Consolata', 'last_name' => 'Temba', 'gender' => 'female', 'department' => 'Sales & Client Services', 'designation' => 'Client Relations Officer', 'salary' => 0, 'hire_years_ago' => 3, 'employment_type' => EmploymentType::FullTime->value, 'status' => EmployeeStatus::Terminated->value, 'payroll_eligible' => false],
        ];

        $employees = [];
        foreach ($rows as $index => $row) {
            $paymentMethod = $row['payment_method'] ?? 'bank_transfer';

            $employees[] = Employee::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'department_id' => $departments[$row['department']]->id,
                'designation_id' => $designations[$row['designation']]->id,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'gender' => $row['gender'],
                'email' => strtolower($row['first_name'].'.'.$row['last_name']).'@traceclearing.co.tz',
                'phone' => '+255 7'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT).' '.str_pad((string) random_int(100000, 999999), 6, '0'),
                'nationality' => 'Tanzanian',
                'marital_status' => $index % 3 === 0 ? 'married' : 'single',
                'residential_address' => 'Kinondoni, Dar es Salaam',
                'emergency_contact_name' => 'Next of Kin '.$row['last_name'],
                'emergency_contact_phone' => '+255 76'.str_pad((string) random_int(1000000, 9999999), 7, '0'),
                'job_title' => $row['designation'],
                'employment_type' => $row['employment_type'],
                'status' => $row['status'],
                'hire_date' => now()->subYears($row['hire_years_ago'])->subDays(random_int(1, 300))->toDateString(),
                'termination_date' => $row['status'] === EmployeeStatus::Terminated->value ? now()->subMonths(2)->toDateString() : null,
                'salary' => $row['salary'],
                'payroll_eligible' => $row['payroll_eligible'] ?? true,
                'preferred_payment_method' => $paymentMethod,
                'pay_currency' => 'TZS',
                'bank_name' => $paymentMethod === 'bank_transfer' ? 'CRDB Bank' : null,
                'bank_account_name' => $paymentMethod === 'bank_transfer' ? $row['first_name'].' '.$row['last_name'] : null,
                'bank_account_number' => $paymentMethod === 'bank_transfer' ? '01'.str_pad((string) random_int(10000000, 99999999), 8, '0') : null,
                'bank_branch_name' => $paymentMethod === 'bank_transfer' ? 'Samora Avenue Branch' : null,
                'mobile_money_provider' => $paymentMethod === 'mobile_money' ? 'M-Pesa' : null,
                'mobile_money_number' => $paymentMethod === 'mobile_money' ? '+255 7'.str_pad((string) random_int(10000000, 99999999), 8, '0') : null,
            ]);
        }

        return $employees;
    }

    private function seedContracts(int $tenantId, array $employees): void
    {
        foreach ($employees as $employee) {
            if ($employee->status === EmployeeStatus::Terminated) {
                continue;
            }

            EmployeeContract::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'employment_type' => $employee->employment_type->value,
                'effective_date' => $employee->hire_date->toDateString(),
                'basic_salary' => $employee->salary,
                'pay_frequency' => PayFrequency::Monthly->value,
                'working_hours_per_week' => 45,
                'probation_period_days' => 90,
                'notice_period_days' => 30,
                'overtime_eligible' => true,
                'commission_eligible' => false,
                'leave_entitlement_days' => 21,
                'status' => ContractStatus::Active->value,
            ]);
        }
    }

    private function seedPublicHolidays(int $tenantId): void
    {
        $year = now()->year;
        $rows = [
            ['name' => 'Union Day', 'date' => "{$year}-04-26"],
            ['name' => 'Sabasaba', 'date' => "{$year}-07-07"],
            ['name' => 'Nane Nane', 'date' => "{$year}-08-08"],
            ['name' => 'Independence Day', 'date' => "{$year}-12-09"],
        ];

        foreach ($rows as $row) {
            PublicHoliday::create($row + ['tenant_id' => $tenantId]);
        }
    }

    private function seedAttendance(int $tenantId, array $employees): void
    {
        foreach ($employees as $employee) {
            if ($employee->status === EmployeeStatus::Terminated) {
                continue;
            }

            $date = now()->subDays(21);
            $daysSeeded = 0;

            while ($daysSeeded < 15) {
                $date->addDay();
                if ($date->isWeekend() || $date->isFuture()) {
                    continue;
                }

                $roll = random_int(1, 100);
                $status = match (true) {
                    $roll <= 82 => 'present',
                    $roll <= 90 => 'late',
                    $roll <= 96 => 'on_leave',
                    default => 'absent',
                };

                \App\Models\AttendanceRecord::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'date' => $date->toDateString(),
                    'status' => $status,
                    'source' => 'manual',
                    'check_in' => in_array($status, ['present', 'late'], true) ? $date->copy()->setTime(8, $status === 'late' ? random_int(15, 45) : random_int(0, 10)) : null,
                    'check_out' => in_array($status, ['present', 'late'], true) ? $date->copy()->setTime(17, random_int(0, 20)) : null,
                ]);

                $daysSeeded++;
            }
        }
    }

    private function seedLeaveTypesAndBalances(int $tenantId, array $employees): array
    {
        $rows = [
            'Annual Leave' => ['is_paid' => true, 'accrual_rule' => 'monthly', 'default_annual_days' => 21, 'carry_forward_max_days' => 7],
            'Sick Leave' => ['is_paid' => true, 'accrual_rule' => 'none', 'default_annual_days' => 14, 'carry_forward_max_days' => 0],
            'Maternity/Paternity Leave' => ['is_paid' => true, 'accrual_rule' => 'none', 'default_annual_days' => 84, 'carry_forward_max_days' => 0],
        ];

        $leaveTypes = collect($rows)->mapWithKeys(
            fn (array $row, string $name) => [$name => LeaveType::create($row + ['tenant_id' => $tenantId, 'name' => $name, 'is_active' => true])]
        )->all();

        $year = now()->year;
        foreach ($employees as $employee) {
            if ($employee->status === EmployeeStatus::Terminated) {
                continue;
            }

            foreach (['Annual Leave', 'Sick Leave'] as $typeName) {
                LeaveBalance::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveTypes[$typeName]->id,
                    'year' => $year,
                    'entitled_days' => $leaveTypes[$typeName]->default_annual_days,
                    'used_days' => 0,
                    'carried_forward_days' => 0,
                ]);
            }
        }

        return $leaveTypes;
    }

    private function seedLeaveRequests(int $tenantId, array $employees, array $leaveTypes, int $ownerId): void
    {
        $service = app(LeaveRequestService::class);
        $annual = $leaveTypes['Annual Leave'];
        $active = array_values(array_filter($employees, fn (Employee $e) => $e->status !== EmployeeStatus::Terminated));

        // One approved (updates the balance), one pending, one rejected.
        if (isset($active[1])) {
            $approved = LeaveRequest::create([
                'tenant_id' => $tenantId, 'employee_id' => $active[1]->id, 'leave_type_id' => $annual->id,
                'start_date' => now()->subDays(10)->toDateString(), 'end_date' => now()->subDays(6)->toDateString(),
                'days' => 5, 'status' => 'pending', 'reason' => 'Family matter upcountry.', 'created_by' => $ownerId,
            ]);
            $service->approve($approved);
        }

        if (isset($active[3])) {
            LeaveRequest::create([
                'tenant_id' => $tenantId, 'employee_id' => $active[3]->id, 'leave_type_id' => $annual->id,
                'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
                'days' => 5, 'status' => 'pending', 'reason' => 'Annual leave request.', 'created_by' => $ownerId,
            ]);
        }

        if (isset($active[4])) {
            $rejected = LeaveRequest::create([
                'tenant_id' => $tenantId, 'employee_id' => $active[4]->id, 'leave_type_id' => $annual->id,
                'start_date' => now()->addDays(2)->toDateString(), 'end_date' => now()->addDays(12)->toDateString(),
                'days' => 11, 'status' => 'pending', 'reason' => 'Requested during peak clearing season.', 'created_by' => $ownerId,
            ]);
            $service->reject($rejected, 'Insufficient staffing coverage during this period — please reschedule.');
        }
    }

    private function seedStatutoryRuleSet(int $tenantId): StatutoryRuleSet
    {
        $ruleSet = StatutoryRuleSet::create([
            'tenant_id' => $tenantId,
            'name' => 'Tanzania PAYE & NSSF (example)',
            'country_code' => 'TZ',
            'description' => 'Illustrative Tanzania-style progressive PAYE bands and an NSSF-style contribution — an editable starting point, not guaranteed-current tax law.',
            'is_default' => true,
            'is_active' => true,
        ]);

        $bands = [
            ['lower_bound' => 0, 'upper_bound' => 270000, 'rate' => 0, 'band_order' => 1],
            ['lower_bound' => 270000, 'upper_bound' => 520000, 'rate' => 8, 'band_order' => 2],
            ['lower_bound' => 520000, 'upper_bound' => 760000, 'rate' => 20, 'band_order' => 3],
            ['lower_bound' => 760000, 'upper_bound' => 1000000, 'rate' => 25, 'band_order' => 4],
            ['lower_bound' => 1000000, 'upper_bound' => null, 'rate' => 30, 'band_order' => 5],
        ];
        foreach ($bands as $band) {
            StatutoryTaxBand::create($band + ['tenant_id' => $tenantId, 'statutory_rule_set_id' => $ruleSet->id]);
        }

        StatutoryContributionRule::create([
            'tenant_id' => $tenantId,
            'statutory_rule_set_id' => $ruleSet->id,
            'code' => 'nssf',
            'name' => 'NSSF',
            'employee_rate' => 10,
            'employer_rate' => 10,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return $ruleSet;
    }

    private function seedPayrollComponents(int $tenantId): array
    {
        $rows = [
            ['code' => 'TRANSPORT', 'name' => 'Transport Allowance', 'type' => PayrollComponentType::Earning->value, 'calculation_method' => PayrollCalculationMethod::Fixed->value, 'amount' => 100000, 'is_taxable' => true, 'is_pensionable' => false, 'sort_order' => 1],
            ['code' => 'HOUSING', 'name' => 'Housing Allowance', 'type' => PayrollComponentType::Earning->value, 'calculation_method' => PayrollCalculationMethod::Percentage->value, 'percentage' => 15, 'percentage_base' => 'basic_salary', 'is_taxable' => true, 'is_pensionable' => false, 'sort_order' => 2],
            ['code' => 'AIRTIME', 'name' => 'Airtime/Communication Allowance', 'type' => PayrollComponentType::Earning->value, 'calculation_method' => PayrollCalculationMethod::Fixed->value, 'amount' => 30000, 'is_taxable' => false, 'is_pensionable' => false, 'sort_order' => 3],
        ];

        return collect($rows)
            ->mapWithKeys(fn (array $row) => [$row['code'] => PayrollComponent::create($row + [
                'tenant_id' => $tenantId,
                'is_recurring' => true,
                'is_active' => true,
                'effective_date' => now()->subYear()->toDateString(),
            ])])
            ->all();
    }

    private function assignPayrollComponents(int $tenantId, array $employees, array $components): void
    {
        foreach ($employees as $employee) {
            if (! $employee->payroll_eligible) {
                continue;
            }

            EmployeePayrollComponent::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'payroll_component_id' => $components['TRANSPORT']->id,
                'effective_date' => $employee->hire_date->toDateString(),
                'is_active' => true,
            ]);
            EmployeePayrollComponent::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'payroll_component_id' => $components['HOUSING']->id,
                'effective_date' => $employee->hire_date->toDateString(),
                'is_active' => true,
            ]);
        }
    }

    /**
     * New payroll-specific accounts only — reuses the tenant's existing
     * bank account for bank_cash_account_id rather than creating a
     * duplicate, since the general chart of accounts (seeded by
     * TraceClearingForwardingDataSeeder, if it has run) may already have
     * one at code 1010. Falls back to creating one if not found, since
     * this seeder must also work standalone against a tenant that never
     * ran the other seeder.
     */
    private function seedPayrollAccounts(int $tenantId): array
    {
        $bankAccount = Account::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => '1010'],
            ['name' => 'Bank Account', 'type' => AccountType::Asset->value, 'is_active' => true],
        );

        $rows = [
            ['code' => '5410', 'name' => 'Salary Expense', 'type' => AccountType::Expense->value],
            ['code' => '5420', 'name' => 'Allowance Expense', 'type' => AccountType::Expense->value],
            ['code' => '5430', 'name' => 'Overtime Expense', 'type' => AccountType::Expense->value],
            ['code' => '5440', 'name' => 'Employer Statutory Contribution Expense', 'type' => AccountType::Expense->value],
            ['code' => '2310', 'name' => 'Payroll Payable', 'type' => AccountType::Liability->value],
            ['code' => '2320', 'name' => 'PAYE Tax Payable', 'type' => AccountType::Liability->value],
            ['code' => '2330', 'name' => 'Statutory Contributions Payable', 'type' => AccountType::Liability->value],
            ['code' => '2340', 'name' => 'Other Payroll Deductions Payable', 'type' => AccountType::Liability->value],
            ['code' => '1510', 'name' => 'Employee Loan Receivable', 'type' => AccountType::Asset->value],
            ['code' => '1520', 'name' => 'Salary Advance Receivable', 'type' => AccountType::Asset->value],
        ];

        $accounts = ['bank_cash' => $bankAccount];
        foreach ($rows as $row) {
            $accounts[$row['code']] = Account::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $row['code']],
                ['name' => $row['name'], 'type' => $row['type'], 'is_active' => true],
            );
        }

        return $accounts;
    }

    private function seedPayrollSettings(int $tenantId, StatutoryRuleSet $ruleSet, array $accounts): void
    {
        PayrollSettings::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'statutory_rule_set_id' => $ruleSet->id,
                'default_pay_frequency' => PayFrequency::Monthly->value,
                'overtime_multiplier' => 1.5,
                'standard_working_days_per_month' => 22,
                'standard_hours_per_day' => 8,
                'salary_expense_account_id' => $accounts['5410']->id,
                'allowance_expense_account_id' => $accounts['5420']->id,
                'overtime_expense_account_id' => $accounts['5430']->id,
                'bonus_expense_account_id' => $accounts['5420']->id,
                'employer_contribution_expense_account_id' => $accounts['5440']->id,
                'payroll_payable_account_id' => $accounts['2310']->id,
                'tax_payable_account_id' => $accounts['2320']->id,
                'statutory_contributions_payable_account_id' => $accounts['2330']->id,
                'loan_receivable_account_id' => $accounts['1510']->id,
                'advance_receivable_account_id' => $accounts['1520']->id,
                'other_deductions_payable_account_id' => $accounts['2340']->id,
                'bank_cash_account_id' => $accounts['bank_cash']->id,
            ],
        );
    }

    /**
     * Creates a payroll period for last month (so the finalized run is a
     * believable already-paid period, not one dated in the future) and
     * drives it through the real calculation + approval engine —
     * calculate() -> submit() -> approve() -> finalize() — so payslip
     * amounts are genuinely computed, not hand-typed. approve()/finalize()
     * consult Auth::user() for the legacy-approval-permission fallback
     * (no ApprovalWorkflow is configured for this tenant), so the owner
     * is logged in for the duration of this call only.
     */
    private function runFullPayrollCycle(int $tenantId, User $owner): void
    {
        $previousUser = Auth::user();
        Auth::login($owner);

        try {
            $periodStart = now()->subMonthNoOverflow()->startOfMonth();
            $periodEnd = now()->subMonthNoOverflow()->endOfMonth();

            $period = PayrollPeriod::create([
                'tenant_id' => $tenantId,
                'name' => $periodStart->format('F Y'),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'payment_date' => $periodEnd->copy()->addDays(3)->toDateString(),
                'pay_frequency' => PayFrequency::Monthly->value,
                'is_locked' => false,
            ]);

            $run = PayrollRun::create([
                'tenant_id' => $tenantId,
                'payroll_period_id' => $period->id,
                'run_number' => 1,
                'status' => 'draft',
                'created_by' => $owner->id,
            ]);

            $run = app(PayrollCalculationService::class)->calculate($run);
            $run = app(PayrollApprovalService::class)->submit($run);
            $run = app(PayrollApprovalService::class)->approve($run);
            app(PayrollApprovalService::class)->finalize($run);
        } finally {
            if ($previousUser) {
                Auth::login($previousUser);
            } else {
                Auth::logout();
            }
        }
    }

    private function seedLoans(int $tenantId, array $employees, int $ownerId): void
    {
        $service = app(LoanService::class);
        $eligible = array_values(array_filter($employees, fn (Employee $e) => $e->payroll_eligible));

        if (isset($eligible[1])) {
            $loan = EmployeeLoan::create([
                'tenant_id' => $tenantId,
                'employee_id' => $eligible[1]->id,
                'principal_amount' => 600000,
                'interest_rate' => 0,
                'number_of_installments' => 6,
                'installment_amount' => 100000,
                'start_date' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
                'status' => 'draft',
                'reason' => 'Emergency medical expense advance.',
                'created_by' => $ownerId,
            ]);
            $service->submit($loan);
            $service->approve($loan);
        }

        if (isset($eligible[6])) {
            EmployeeLoan::create([
                'tenant_id' => $tenantId,
                'employee_id' => $eligible[6]->id,
                'principal_amount' => 300000,
                'interest_rate' => 0,
                'number_of_installments' => 3,
                'installment_amount' => 100000,
                'start_date' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
                'status' => 'draft',
                'reason' => 'School fees advance.',
                'created_by' => $ownerId,
            ]);
        }
    }

    private function seedAssets(int $tenantId, array $employees, int $ownerId): void
    {
        $assignments = [
            [0, AssetType::Laptop->value, 'Dell Latitude 5420', 'DL5420-'],
            [0, AssetType::Phone->value, 'Samsung Galaxy A54', 'SGA54-'],
            [1, AssetType::Phone->value, 'Samsung Galaxy A34', 'SGA34-'],
            [4, AssetType::Laptop->value, 'HP ProBook 450', 'HPB450-'],
            [8, AssetType::Vehicle->value, 'Isuzu FVR 34 Truck', 'ISZ-FVR-'],
            [10, AssetType::Vehicle->value, 'Isuzu NPR Truck', 'ISZ-NPR-'],
        ];

        foreach ($assignments as [$index, $type, $name, $serialPrefix]) {
            if (! isset($employees[$index])) {
                continue;
            }

            EmployeeAsset::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employees[$index]->id,
                'asset_type' => $type,
                'asset_name' => $name,
                'serial_number' => $serialPrefix.random_int(1000, 9999),
                'assigned_date' => now()->subMonths(random_int(1, 12))->toDateString(),
                'condition_at_assignment' => 'Good',
                'status' => EmployeeAssetStatus::Assigned->value,
                'created_by' => $ownerId,
            ]);
        }
    }

    private function seedPerformanceReviews(int $tenantId, array $employees, int $ownerId): void
    {
        $active = array_values(array_filter($employees, fn (Employee $e) => $e->status !== EmployeeStatus::Terminated));
        $periodStart = now()->subMonths(6)->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $statuses = [
            ['status' => PerformanceReviewStatus::Acknowledged->value, 'employee_comments' => 'Thank you for the feedback, I will keep improving on documentation turnaround.'],
            ['status' => PerformanceReviewStatus::Submitted->value, 'employee_comments' => null],
            ['status' => PerformanceReviewStatus::Draft->value, 'employee_comments' => null],
        ];

        foreach ($statuses as $index => $row) {
            if (! isset($active[$index])) {
                continue;
            }

            PerformanceReview::create([
                'tenant_id' => $tenantId,
                'employee_id' => $active[$index]->id,
                'reviewer_id' => $ownerId,
                'review_period_start' => $periodStart->toDateString(),
                'review_period_end' => $periodEnd->toDateString(),
                'review_date' => $periodEnd->copy()->addDays(5)->toDateString(),
                'overall_rating' => 4.0,
                'kpi_scores' => ['punctuality' => 4, 'quality_of_work' => 4, 'teamwork' => 5, 'client_handling' => 4],
                'strengths' => 'Reliable, detail-oriented, and responsive to client requests.',
                'areas_for_improvement' => 'Could improve on proactive status updates to clients.',
                'goals' => 'Reduce average documentation turnaround time by 10% next quarter.',
                'comments' => 'Solid performance this review period.',
                'employee_comments' => $row['employee_comments'],
                'status' => $row['status'],
                'acknowledged_at' => $row['status'] === PerformanceReviewStatus::Acknowledged->value ? now()->subDays(3) : null,
                'created_by' => $ownerId,
            ]);
        }
    }
}
