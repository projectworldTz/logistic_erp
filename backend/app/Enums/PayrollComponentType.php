<?php

namespace App\Enums;

enum PayrollComponentType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
    case EmployerContribution = 'employer_contribution';
}
