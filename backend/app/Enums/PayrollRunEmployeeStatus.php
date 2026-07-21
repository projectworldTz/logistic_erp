<?php

namespace App\Enums;

enum PayrollRunEmployeeStatus: string
{
    case Included = 'included';
    case Excluded = 'excluded';
    case Exception = 'exception';
}
