<?php

namespace App\Services\Tracking;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ShipmentTrackingQrService
{
    /**
     * Build an SVG QR code (no Imagick dependency, pure PHP) encoding the
     * public tracking URL for a shipment's tracking code.
     */
    public function generateSvg(string $trackingCode): string
    {
        $url = rtrim(config('app.frontend_url'), '/')."/track/{$trackingCode}";

        return QrCode::size(280)->margin(1)->generate($url);
    }

    public function trackingUrl(string $trackingCode): string
    {
        return rtrim(config('app.frontend_url'), '/')."/track/{$trackingCode}";
    }
}
