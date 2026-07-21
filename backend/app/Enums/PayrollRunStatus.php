<?php

namespace App\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Finalized = 'finalized';
}
