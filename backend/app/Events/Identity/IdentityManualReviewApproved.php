<?php

namespace App\Events\Identity;

use App\Models\EmployeeIdentityManualReview;
use Illuminate\Foundation\Events\Dispatchable;

class IdentityManualReviewApproved
{
    use Dispatchable;

    public function __construct(public readonly EmployeeIdentityManualReview $review) {}
}
