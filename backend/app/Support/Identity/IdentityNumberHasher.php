<?php

namespace App\Support\Identity;

/**
 * Identity numbers are stored encrypted (unreadable at rest, unqueryable)
 * and never in plain text — but duplicate detection and tenant-scoped
 * uniqueness both need an exact-match lookup. This produces a one-way,
 * keyed hash of a normalized identity number for that purpose. Never use
 * this to reconstruct the original number.
 */
class IdentityNumberHasher
{
    public static function hash(string $identityNumber, string $documentType, int $tenantId): string
    {
        $normalized = strtoupper(preg_replace('/[\s\-]/', '', $identityNumber));

        return hash_hmac('sha256', "{$tenantId}|{$documentType}|{$normalized}", config('app.key'));
    }

    public static function mask(string $identityNumber): string
    {
        $length = strlen($identityNumber);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $visibleStart = min(4, $length - 2);

        return substr($identityNumber, 0, $visibleStart).str_repeat('*', $length - $visibleStart - 2).substr($identityNumber, -2);
    }
}
