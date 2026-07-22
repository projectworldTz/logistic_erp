<?php

namespace App\Contracts;

use App\Services\Identity\Data\IdentityVerificationRequestData;
use App\Services\Identity\Data\VerificationResult;

/**
 * A pluggable source of truth for "does this identity document belong to
 * this person". IdentityVerificationService resolves exactly one
 * implementation from the container per app\config\identity.php — nothing
 * else in the app (controllers, other services, the frontend) knows or
 * cares which provider answered.
 *
 * Implementations should throw one of the App\Services\Identity\Exceptions
 * classes for anticipated failure modes (not found, expired, provider
 * unavailable, rate limited, ...) rather than returning a failed
 * VerificationResult directly — IdentityVerificationService is the single
 * place that catches those and turns them into a normalized result.
 */
interface IdentityVerificationProvider
{
    /**
     * Attempt to verify the given identity document. Throws an
     * App\Services\Identity\Exceptions\IdentityVerificationException
     * subclass on any anticipated failure; only returns when the
     * identity was actually found and matched.
     */
    public function verify(IdentityVerificationRequestData $request): VerificationResult;

    /**
     * Short machine key for this provider (e.g. "mock", "nida") — stored
     * on verification records and shown in the admin provider settings
     * page.
     */
    public function key(): string;

    /**
     * Human-readable name shown in the UI (e.g. "Test Provider (Mock)").
     */
    public function displayName(): string;

    /**
     * Whether this is a real, production-connected provider. False for
     * the mock provider and any provider not yet activated — the
     * frontend uses this to render a "Test Provider" badge.
     */
    public function isLive(): bool;
}
