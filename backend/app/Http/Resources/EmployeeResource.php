<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Deliberately excludes salary/bank/national-ID — those are only ever
     * returned by EmployeeSalaryResource behind the separate
     * hr.employees.salary.view permission (see EmployeeSalaryController).
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'designation_id' => $this->designation_id,
            'designation' => new DesignationResource($this->whenLoaded('designation')),
            'reporting_manager_id' => $this->reporting_manager_id,
            'reporting_manager' => new EmployeeResource($this->whenLoaded('reportingManager')),
            'name' => $this->name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'nationality' => $this->nationality,
            'marital_status' => $this->marital_status,
            'photo_path' => $this->photo_path,
            'email' => $this->email,
            'phone' => $this->phone,
            'alternative_phone' => $this->alternative_phone,
            'residential_address' => $this->residential_address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'job_title' => $this->job_title,
            'employee_category' => $this->employee_category,
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'hire_date' => $this->hire_date,
            'confirmation_date' => $this->confirmation_date,
            'probation_end_date' => $this->probation_end_date,
            'termination_date' => $this->termination_date,
            'work_location' => $this->work_location,
            'payroll_eligible' => $this->payroll_eligible,
            'notice_period_days' => $this->notice_period_days,
            'pay_currency' => $this->pay_currency,
            'preferred_payment_method' => $this->preferred_payment_method,
            'statutory_details' => $this->statutory_details,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
