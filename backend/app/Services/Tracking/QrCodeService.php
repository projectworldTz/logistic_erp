<?php

namespace App\Services\Tracking;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /**
     * Build an SVG QR code (no Imagick dependency, pure PHP) encoding an
     * arbitrary verification URL.
     */
    public function svg(string $url, int $size = 280): string
    {
        return QrCode::size($size)->margin(1)->generate($url);
    }
}
