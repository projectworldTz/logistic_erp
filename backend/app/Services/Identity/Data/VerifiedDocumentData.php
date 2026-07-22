<?php

namespace App\Services\Identity\Data;

use App\Enums\IdentityDocumentType;

final class VerifiedDocumentData
{
    public function __construct(
        public readonly IdentityDocumentType $type,
        public readonly string $numberMasked,
        public readonly string $status,
        public readonly ?string $expiryDate = null,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'number_masked' => $this->numberMasked,
            'status' => $this->status,
            'expiry_date' => $this->expiryDate,
        ];
    }
}
