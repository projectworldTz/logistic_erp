<?php

namespace App\Enums;

enum ApprovalDecisionType: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
