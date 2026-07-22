<?php

namespace App\Enums;

enum IdentityManualReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
