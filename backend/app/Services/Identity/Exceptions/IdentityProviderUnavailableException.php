<?php

namespace App\Services\Identity\Exceptions;

use App\Enums\IdentityVerificationStatus;

class IdentityProviderUnavailableException extends IdentityVerificationException
{
    protected $message = 'The identity provider is temporarily unavailable.';

    public function status(): IdentityVerificationStatus
    {
        return IdentityVerificationStatus::ProviderUnavailable;
    }
}
