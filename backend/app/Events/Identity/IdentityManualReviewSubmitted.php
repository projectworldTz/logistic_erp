<?php

namespace App\Events\Identity;

use App\Models\EmployeeIdentityManualReview;
use Illuminate\Foundation\Events\Dispatchable;

class IdentityManualReviewSubmitted
{
    use Dispatchable;

    public function __construct(public readonly EmployeeIdentityManualReview $review) {}
}
