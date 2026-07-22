<?php

namespace App\Services\Identity\Providers;

use App\Contracts\IdentityVerificationProvider;
use App\Services\Identity\Data\IdentityVerificationRequestData;
use App\Services\Identity\Data\VerificationResult;
use App\Services\Identity\Exceptions\IdentityProviderUnavailableException;
use RuntimeException;

/**
 * Placeholder for the official NIDA (Tanzania National Identification
 * Authority) integration. Deliberately unimplemented — no NIDA endpoint,
 * request/response shape, or authentication method has been invented
 * here, because none of that is public or has been provided yet.
 *
 * DO NOT enable this provider (IDENTITY_PROVIDER=nida) until:
 *   - Official NIDA API documentation has been obtained.
 *   - Production credentials (client_id/secret/certificate) have been
 *     issued and approved for this application.
 *   - The exact request/response contract is known, so it can be mapped
 *     into VerificationResult/VerifiedPersonData/VerifiedDocumentData
 *     without guessing at field names.
 *
 * When that happens, the only work required is inside this one class:
 *   1. Implement the HTTP call in verify() using the config values already
 *      wired in config/identity.php ('nida' => [...]) — base_url,
 *      client_id, client_secret, certificate_path, timeout.
 *   2. Map the official response onto VerifiedPersonData/VerifiedDocumentData.
 *   3. Translate documented NIDA error responses onto the existing
 *      App\Services\Identity\Exceptions\* classes (IdentityNotFoundException,
 *      IdentityDocumentExpiredException, IdentityProviderUnavailableException,
 *      etc.) so IdentityVerificationService needs no changes.
 *   4. Set IDENTITY_PROVIDER=nida in .env.
 * No controller, route, frontend component, or service class should need
 * to change — IdentityProviderFactory resolves whichever provider is
 * configured, and everything downstream only ever talks to the
 * IdentityVerificationProvider contract.
 */
class OfficialNidaProvider implements IdentityVerificationProvider
{
    public function __construct(private readonly array $config) {}

    public function key(): string
    {
        return 'nida';
    }

    public function displayName(): string
    {
        return 'NIDA Tanzania (Not Configured)';
    }

    public function isLive(): bool
    {
        return false;
    }

    public function verify(IdentityVerificationRequestData $request): VerificationResult
    {
        // TODO: implement the real NIDA API call here once credentials and
        // official documentation are available (see class docblock).
        throw new IdentityProviderUnavailableException(
            'The official NIDA identity provider is not yet configured. Set IDENTITY_PROVIDER=mock or implement OfficialNidaProvider.'
        );
    }

    public static function assertNeverUsedInProduction(): void
    {
        if (app()->environment('production') && config('identity.provider') === 'nida') {
            throw new RuntimeException(
                'IDENTITY_PROVIDER=nida is set but OfficialNidaProvider is an unimplemented placeholder. Refusing to boot with a fake government identity provider active in production.'
            );
        }
    }
}
