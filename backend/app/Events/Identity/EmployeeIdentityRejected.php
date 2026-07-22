<?php

namespace App\Events\Identity;

use App\Models\EmployeeIdentityVerification;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EmployeeIdentityRejected
{
    use Dispatchable;

    public function __construct(
        public readonly EmployeeIdentityVerification $verification,
        public readonly User $rejectedBy,
    ) {}
}
