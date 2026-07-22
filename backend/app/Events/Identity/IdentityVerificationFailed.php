<?php

namespace App\Events\Identity;

use App\Models\EmployeeIdentityVerification;
use Illuminate\Foundation\Events\Dispatchable;

class IdentityVerificationFailed
{
    use Dispatchable;

    public function __construct(
        public readonly EmployeeIdentityVerification $verification,
        public readonly string $reason,
    ) {}
}
