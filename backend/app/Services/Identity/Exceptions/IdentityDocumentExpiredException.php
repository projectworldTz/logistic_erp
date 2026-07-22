<?php

namespace App\Services\Identity\Exceptions;

use App\Enums\IdentityVerificationStatus;

class IdentityDocumentExpiredException extends IdentityVerificationException
{
    protected $message = 'The identity document has expired.';

    public function status(): IdentityVerificationStatus
    {
        return IdentityVerificationStatus::Expired;
    }
}
