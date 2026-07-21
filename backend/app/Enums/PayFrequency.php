<?php

namespace App\Enums;

enum PayFrequency: string
{
    case Monthly = 'monthly';
    case Biweekly = 'biweekly';
    case Weekly = 'weekly';
    case Daily = 'daily';
    case Hourly = 'hourly';
}
