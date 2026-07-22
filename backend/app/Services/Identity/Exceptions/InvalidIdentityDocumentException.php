<?php

namespace App\Services\Identity\Exceptions;

use App\Enums\IdentityVerificationStatus;

class InvalidIdentityDocumentException extends IdentityVerificationException
{
    protected $message = 'The identity document type or number is not valid.';

    public function status(): IdentityVerificationStatus
    {
        return IdentityVerificationStatus::Failed;
    }
}
