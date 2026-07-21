<?php

namespace App\Enums;

enum PayrollEarningSource: string
{
    case Basic = 'basic';
    case Component = 'component';
    case Overtime = 'overtime';
}
