<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The sensitive counterpart to EmployeeResource — salary/bank/national-ID.
 * Only ever returned from the dedicated GET /employees/{id}/salary route,
 * gated by hr.employees.salary.view, separate from hr.employees.view.
 */
class EmployeeSalaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->id,
            'salary' => $this->salary,
            'pay_currency' => $this->pay_currency,
            'preferred_payment_method' => $this->preferred_payment_method,
            'bank_name' => $this->bank_name,
            'bank_account_name' => $this->bank_account_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_branch_name' => $this->bank_branch_name,
            'mobile_money_provider' => $this->mobile_money_provider,
            'mobile_money_number' => $this->mobile_money_number,
            'national_id_number' => $this->national_id_number,
        ];
    }
}
