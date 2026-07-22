<?php

namespace App\Services\Identity\Exceptions;

use App\Enums\IdentityVerificationStatus;

/**
 * The provider responded but the supplied information does not match the
 * identity record (or the record is inactive) — a mismatch, not a system
 * failure.
 */
class IdentityVerificationFailedException extends IdentityVerificationException
{
    public function __construct(string $message = 'The entered information does not match the identity record.', private readonly IdentityVerificationStatus $resultStatus = IdentityVerificationStatus::Failed)
    {
        parent::__construct($message);
    }

    public function status(): IdentityVerificationStatus
    {
        return $this->resultStatus;
    }
}
