<?php

namespace App\Enums;

enum DisciplinaryCategory: string
{
    case Attendance = 'attendance';
    case Conduct = 'conduct';
    case Performance = 'performance';
    case Safety = 'safety';
    case PolicyViolation = 'policy_violation';
    case Other = 'other';
}
