<?php

namespace App\Services\Identity\Data;

use App\Enums\IdentityDocumentType;

/**
 * Everything a provider needs to attempt a verification. Deliberately
 * broader than a bare NIN string — a passport or future provider may need
 * a date of birth or phone number to disambiguate a match, and metadata
 * carries anything provider-specific without widening this contract.
 */
final class IdentityVerificationRequestData
{
    public function __construct(
        public readonly IdentityDocumentType $documentType,
        public readonly string $identityNumber,
        public readonly string $countryCode,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $phoneNumber = null,
        public readonly array $metadata = [],
    ) {}
}
