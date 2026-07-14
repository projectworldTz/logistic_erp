<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorService
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * Build the otpauth:// provisioning URI an authenticator app scans via
     * QR code (standard Key URI Format). The caller renders this string
     * into an SVG QR code with the same library added in the QR codes
     * phase — no external QR/2FA API dependency needed.
     */
    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = rawurlencode(config('app.name'));
        $label = rawurlencode("{$issuer}:{$user->email}");

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code);
    }

    /**
     * Render a provisioning URI as an SVG QR code (same pure-PHP, no-Imagick
     * infrastructure used for shipment tracking QR codes).
     */
    public function qrCodeSvg(string $provisioningUri): string
    {
        return QrCode::size(220)->margin(1)->generate($provisioningUri);
    }

    /**
     * Generate a fresh batch of one-time recovery codes, shown to the user
     * exactly once at enable time (only the hashed/encrypted form persists).
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }
}
