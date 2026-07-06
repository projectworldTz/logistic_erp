<?php

namespace App\Enums;

enum DemurrageChargeStatus: string
{
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Waived = 'waived';
}
