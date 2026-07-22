<?php

namespace App\Services\Identity\Exceptions;

use App\Enums\IdentityVerificationStatus;

class IdentityRateLimitException extends IdentityVerificationException
{
    protected $message = 'Too many verification attempts. Try again later.';

    public function status(): IdentityVerificationStatus
    {
        return IdentityVerificationStatus::RateLimited;
    }
}
