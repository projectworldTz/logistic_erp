<?php

namespace App\Enums;

enum TimesheetStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
