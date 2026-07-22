<?php

namespace App\Services\Identity\Exceptions;

use App\Enums\IdentityVerificationStatus;
use RuntimeException;

/**
 * Base for every identity-verification failure. The message on these
 * exceptions is always safe to show a user directly — callers must never
 * surface a raw provider exception message instead.
 */
abstract class IdentityVerificationException extends RuntimeException
{
    abstract public function status(): IdentityVerificationStatus;
}
