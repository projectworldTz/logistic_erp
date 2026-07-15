<?php

namespace App\Enums;

enum DetentionChargeStatus: string
{
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Waived = 'waived';
}
