<?php

namespace App\Enums;

use App\Models\EmployeeContract;
use App\Models\EmployeeLoan;
use App\Models\Expense;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\Quotation;
use App\Models\SalaryAdvance;

enum WorkflowSubjectType: string
{
    case Expense = 'expense';
    case Quotation = 'quotation';
    case EmployeeContract = 'employee_contract';
    case LeaveRequest = 'leave_request';
    case PayrollRun = 'payroll_run';
    case EmployeeLoan = 'employee_loan';
    case SalaryAdvance = 'salary_advance';
    case OvertimeRequest = 'overtime_request';

    public function modelClass(): string
    {
        return match ($this) {
            self::Expense => Expense::class,
            self::Quotation => Quotation::class,
            self::EmployeeContract => EmployeeContract::class,
            self::LeaveRequest => LeaveRequest::class,
            self::PayrollRun => PayrollRun::class,
            self::EmployeeLoan => EmployeeLoan::class,
            self::SalaryAdvance => SalaryAdvance::class,
            self::OvertimeRequest => OvertimeRequest::class,
        };
    }
}
