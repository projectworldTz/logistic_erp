<?php

namespace App\Services\Hr;

use App\Enums\EmployeeStatus;
use App\Enums\JobApplicationStatus;
use App\Models\Employee;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Converts an accepted candidate into a real Employee record and kicks
 * off onboarding — the one place recruitment data crosses into the
 * actual HR/payroll employee record.
 */
class RecruitmentService
{
    public function __construct(private readonly OnboardingService $onboardingService) {}

    public function hire(JobApplication $application, array $employeeOverrides = []): Employee
    {
        abort_if($application->status === JobApplicationStatus::Hired, 409, 'This application has already been converted to an employee.');

        return DB::transaction(function () use ($application, $employeeOverrides) {
            $candidate = $application->candidate;

            $employee = Employee::query()->create([
                'tenant_id' => $application->tenant_id,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'department_id' => $application->vacancy->department_id,
                'designation_id' => $application->vacancy->designation_id,
                'branch_id' => $application->vacancy->branch_id,
                'job_title' => $application->vacancy->title,
                'employment_type' => $employeeOverrides['employment_type'] ?? 'full_time',
                'status' => EmployeeStatus::Probation,
                'hire_date' => $employeeOverrides['hire_date'] ?? now()->toDateString(),
                'payroll_eligible' => true,
            ])->refresh();

            $application->update([
                'status' => JobApplicationStatus::Hired,
                'converted_employee_id' => $employee->id,
            ]);

            $this->onboardingService->startForEmployee($employee);

            return $employee;
        });
    }
}
