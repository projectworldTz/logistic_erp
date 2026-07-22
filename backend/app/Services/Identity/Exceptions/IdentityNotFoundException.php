<?php

namespace App\Services\Identity\Exceptions;

use App\Enums\IdentityVerificationStatus;

class IdentityNotFoundException extends IdentityVerificationException
{
    protected $message = 'Identity not found.';

    public function status(): IdentityVerificationStatus
    {
        return IdentityVerificationStatus::NotFound;
    }
}
