<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\SmsChannel;
use Illuminate\Support\Facades\Log;

/**
 * Default SMS driver: writes to the application log instead of calling a
 * real telco API. Safe zero-config fallback for local/dev environments —
 * swap the SmsChannel binding in AppServiceProvider for a real provider
 * (Twilio, Vonage, Africa's Talking, ...) in production.
 */
class LogSmsChannel implements SmsChannel
{
    public function send(string $to, string $message): void
    {
        Log::info("[SMS] to {$to}: {$message}");
    }
}
