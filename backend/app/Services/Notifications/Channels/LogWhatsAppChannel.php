<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\WhatsAppChannel;
use Illuminate\Support\Facades\Log;

/**
 * Default WhatsApp driver: writes to the application log instead of calling
 * a real provider. Safe zero-config fallback for local/dev environments —
 * swap the WhatsAppChannel binding in AppServiceProvider for a real
 * provider (Meta Cloud API, Twilio WhatsApp, ...) in production.
 */
class LogWhatsAppChannel implements WhatsAppChannel
{
    public function send(string $to, string $message): void
    {
        Log::info("[WhatsApp] to {$to}: {$message}");
    }
}
