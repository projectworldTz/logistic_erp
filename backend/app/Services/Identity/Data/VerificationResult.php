<?php

namespace App\Services\Identity\Data;

use App\Enums\IdentityVerificationStatus;

/**
 * The normalized outcome of a single provider call. Every provider —
 * mock or real — must return this shape so the rest of the app never
 * branches on which provider answered.
 */
final class VerificationResult
{
    public function __construct(
        public readonly bool $verified,
        public readonly IdentityVerificationStatus $status,
        public readonly string $provider,
        public readonly ?string $reference,
        public readonly ?string $message,
        public readonly ?VerifiedPersonData $person,
        public readonly ?VerifiedDocumentData $document,
        public readonly ?string $errorCode = null,
        public readonly array $raw = [],
        public readonly ?string $verifiedAt = null,
    ) {}

    public static function success(
        string $provider,
        string $reference,
        VerifiedPersonData $person,
        VerifiedDocumentData $document,
        array $raw = [],
    ): self {
        return new self(
            verified: true,
            status: IdentityVerificationStatus::Verified,
            provider: $provider,
            reference: $reference,
            message: null,
            person: $person,
            document: $document,
            raw: $raw,
            verifiedAt: now()->toIso8601String(),
        );
    }

    public static function failure(
        IdentityVerificationStatus $status,
        string $provider,
        string $message,
        ?string $errorCode = null,
        ?string $reference = null,
        array $raw = [],
    ): self {
        return new self(
            verified: false,
            status: $status,
            provider: $provider,
            reference: $reference,
            message: $message,
            person: null,
            document: null,
            errorCode: $errorCode,
            raw: $raw,
        );
    }

    public function toArray(): array
    {
        return [
            'verified' => $this->verified,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'reference' => $this->reference,
            'message' => $this->message,
            'error_code' => $this->errorCode,
            'person' => $this->person?->toArray(),
            'document' => $this->document?->toArray(),
            'verified_at' => $this->verifiedAt,
        ];
    }
}
