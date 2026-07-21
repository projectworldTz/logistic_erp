<?php

namespace App\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Intern = 'intern';
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Casual = 'casual';
    case Consultant = 'consultant';
    case Driver = 'driver';
    case CommissionBased = 'commission_based';
    case DailyPaid = 'daily_paid';
}
