<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeSalaryResource;
use App\Models\Employee;

class EmployeeSalaryController extends Controller
{
    /**
     * Deliberately its own route gated by hr.employees.salary.view — kept
     * separate from EmployeeController@show (hr.employees.view) so a manager
     * can see the employee record without seeing pay/bank details.
     */
    public function show(Employee $employee)
    {
        return new EmployeeSalaryResource($employee);
    }
}
