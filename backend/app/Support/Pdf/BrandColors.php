<?php

namespace App\Support\Pdf;

/**
 * Turns a tenant's chosen brand hex color into the small palette a PDF
 * template needs (a light tint for section backgrounds, the solid color
 * for accents/bands) — dompdf has no CSS custom properties or color-mix(),
 * so this precomputes the blended hex values in PHP instead.
 */
class BrandColors
{
    private const DEFAULT_PRIMARY = '#2a5fb4';

    public static function forCompany(?string $primaryColor): array
    {
        $primary = self::isValidHex($primaryColor) ? $primaryColor : self::DEFAULT_PRIMARY;

        return [
            'primary' => $primary,
            'primaryLight' => self::lighten($primary, 0.90),
            'primaryLighter' => self::lighten($primary, 0.96),
        ];
    }

    private static function isValidHex(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /**
     * Blends $hex toward white by $amount (0 = unchanged, 1 = white).
     */
    private static function lighten(string $hex, float $amount): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        $blend = fn (int $channel) => (int) round($channel + ($amount * (255 - $channel)));

        return sprintf('#%02x%02x%02x', $blend($r), $blend($g), $blend($b));
    }
}
