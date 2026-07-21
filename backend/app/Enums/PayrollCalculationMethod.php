<?php

namespace App\Enums;

enum PayrollCalculationMethod: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case Formula = 'formula';
}
