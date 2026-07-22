<?php

namespace App\Services\Identity\Providers;

use App\Contracts\IdentityVerificationProvider;
use App\Enums\IdentityDocumentType;
use App\Services\Identity\Data\IdentityVerificationRequestData;
use App\Services\Identity\Data\VerificationResult;
use App\Services\Identity\Data\VerifiedDocumentData;
use App\Services\Identity\Data\VerifiedPersonData;
use App\Services\Identity\Exceptions\IdentityDocumentExpiredException;
use App\Services\Identity\Exceptions\IdentityNotFoundException;
use App\Services\Identity\Exceptions\IdentityProviderUnavailableException;
use App\Services\Identity\Exceptions\IdentityRateLimitException;
use App\Services\Identity\Exceptions\IdentityVerificationFailedException;
use Illuminate\Support\Str;

/**
 * Deterministic, in-memory stand-in for a real national-identity registry.
 * Every identity number below is a fixed test fixture — the same input
 * always produces the same output, so automated tests and manual QA can
 * rely on specific numbers to exercise specific outcomes. No network call,
 * no randomness, nothing resembling a real citizen.
 */
class MockIdentityProvider implements IdentityVerificationProvider
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const IDENTITIES = [
        '199206121234500001' => [
            'first_name' => 'John', 'middle_name' => 'Peter', 'last_name' => 'Mrema',
            'date_of_birth' => '1992-06-12', 'gender' => 'Male', 'nationality' => 'Tanzanian',
            'avatar_color' => '#2563eb', 'document_status' => 'active', 'expiry_date' => null,
        ],
        '198503031234500002' => [
            'first_name' => 'Grace', 'middle_name' => 'Michael', 'last_name' => 'Kileo',
            'date_of_birth' => '1985-03-03', 'gender' => 'Female', 'nationality' => 'Tanzanian',
            'avatar_color' => '#16a34a', 'document_status' => 'active', 'expiry_date' => null,
        ],
        '197811151234500003' => [
            'first_name' => 'Ibrahim', 'middle_name' => 'Said', 'last_name' => 'Ally',
            'date_of_birth' => '1978-11-15', 'gender' => 'Male', 'nationality' => 'Tanzanian',
            'avatar_color' => '#d97706', 'document_status' => 'active', 'expiry_date' => null,
        ],
        '199510221234500004' => [
            'first_name' => 'Neema', 'middle_name' => 'Elias', 'last_name' => 'Mushi',
            'date_of_birth' => '1995-10-22', 'gender' => 'Female', 'nationality' => 'Tanzanian',
            'avatar_color' => '#9333ea', 'document_status' => 'active', 'expiry_date' => null,
        ],
        '196708081234500005' => [
            'first_name' => 'Fatuma', 'middle_name' => 'Rajabu', 'last_name' => 'Kessy',
            'date_of_birth' => '1967-08-08', 'gender' => 'Female', 'nationality' => 'Tanzanian',
            'avatar_color' => '#dc2626', 'document_status' => 'active', 'expiry_date' => null,
        ],
        // Passport-document fixture, so PassportIdentityProvider-shaped flows have coverage too.
        'AB1234567' => [
            'first_name' => 'David', 'middle_name' => null, 'last_name' => 'Komba',
            'date_of_birth' => '1990-01-20', 'gender' => 'Male', 'nationality' => 'Tanzanian',
            'avatar_color' => '#0891b2', 'document_status' => 'active', 'expiry_date' => '2029-01-19',
        ],
    ];

    /**
     * Magic numbers that deterministically trigger each documented failure
     * mode instead of a lookup, so tests never depend on randomness.
     */
    private const NOT_FOUND_NUMBER = '000000000000000001';

    private const INACTIVE_NUMBER = '000000000000000002';

    private const EXPIRED_NUMBER = '000000000000000003';

    private const PROVIDER_UNAVAILABLE_NUMBER = '000000000000000004';

    private const MISMATCH_NUMBER = '000000000000000005';

    private const RATE_LIMITED_NUMBER = '000000000000000006';

    public function key(): string
    {
        return 'mock';
    }

    public function displayName(): string
    {
        return 'Test Provider (Mock)';
    }

    public function isLive(): bool
    {
        return false;
    }

    public function verify(IdentityVerificationRequestData $request): VerificationResult
    {
        $number = $request->identityNumber;

        match ($number) {
            self::NOT_FOUND_NUMBER => throw new IdentityNotFoundException,
            self::INACTIVE_NUMBER => throw new IdentityVerificationFailedException(
                'The identity document is inactive.',
                \App\Enums\IdentityVerificationStatus::Inactive,
            ),
            self::EXPIRED_NUMBER => throw new IdentityDocumentExpiredException,
            self::PROVIDER_UNAVAILABLE_NUMBER => throw new IdentityProviderUnavailableException,
            self::MISMATCH_NUMBER => throw new IdentityVerificationFailedException,
            self::RATE_LIMITED_NUMBER => throw new IdentityRateLimitException,
            default => null,
        };

        $identity = self::IDENTITIES[$number] ?? null;

        if (! $identity) {
            throw new IdentityNotFoundException;
        }

        $fullName = trim(implode(' ', array_filter([
            $identity['first_name'], $identity['middle_name'], $identity['last_name'],
        ])));

        $person = new VerifiedPersonData(
            firstName: $identity['first_name'],
            middleName: $identity['middle_name'],
            lastName: $identity['last_name'],
            fullName: $fullName,
            dateOfBirth: $identity['date_of_birth'],
            gender: $identity['gender'],
            nationality: $identity['nationality'],
            countryCode: $request->countryCode,
            photoUrl: $this->avatarDataUri($identity['first_name'], $identity['last_name'], $identity['avatar_color']),
        );

        $document = new VerifiedDocumentData(
            type: $request->documentType,
            numberMasked: $this->mask($number),
            status: $identity['document_status'],
            expiryDate: $identity['expiry_date'],
        );

        $reference = sprintf('MOCK-IDENTITY-%s-%s', now()->format('Ymd'), Str::upper(Str::random(6)));

        return VerificationResult::success(
            provider: $this->key(),
            reference: $reference,
            person: $person,
            document: $document,
            raw: ['source' => 'mock-fixture'],
        );
    }

    private function mask(string $number): string
    {
        $length = strlen($number);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($number, 0, 4).str_repeat('*', $length - 6).substr($number, -2);
    }

    /**
     * A tiny inline SVG "photo" so the verification card always has
     * something to render without needing physical fixture image files
     * committed to the repo.
     */
    private function avatarDataUri(string $firstName, string $lastName, string $color): string
    {
        $initials = strtoupper(substr($firstName, 0, 1).substr($lastName, 0, 1));
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="240" height="240">
            <rect width="240" height="240" fill="{$color}" />
            <text x="120" y="140" font-family="Arial, sans-serif" font-size="90" fill="#ffffff" text-anchor="middle">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @return array<int, IdentityDocumentType>
     */
    public static function supportedDocumentTypes(): array
    {
        return [IdentityDocumentType::NationalId, IdentityDocumentType::Passport, IdentityDocumentType::Other];
    }
}
