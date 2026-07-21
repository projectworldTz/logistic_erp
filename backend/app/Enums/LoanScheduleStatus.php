<?php

namespace App\Enums;

enum LoanScheduleStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Skipped = 'skipped';
}
