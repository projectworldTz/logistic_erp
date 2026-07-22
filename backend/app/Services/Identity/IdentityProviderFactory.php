<?php

namespace App\Services\Identity;

use App\Contracts\IdentityVerificationProvider;
use App\Services\Identity\Providers\MockIdentityProvider;
use App\Services\Identity\Providers\OfficialNidaProvider;
use InvalidArgumentException;

class IdentityProviderFactory
{
    public static function make(): IdentityVerificationProvider
    {
        $key = config('identity.provider', 'mock');
        $providerConfig = config("identity.providers.{$key}");

        return match ($providerConfig['driver'] ?? $key) {
            'mock' => new MockIdentityProvider,
            'nida' => new OfficialNidaProvider($providerConfig ?? []),
            default => throw new InvalidArgumentException("Unknown identity provider [{$key}]."),
        };
    }
}
