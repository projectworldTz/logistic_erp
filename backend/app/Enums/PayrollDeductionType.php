<?php

namespace App\Enums;

enum PayrollDeductionType: string
{
    case StatutoryTax = 'statutory_tax';
    case StatutoryContribution = 'statutory_contribution';
    case Component = 'component';
    case Absence = 'absence';
    case Loan = 'loan';
    case SalaryAdvance = 'salary_advance';
    case Other = 'other';
}
