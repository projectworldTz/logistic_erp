<?php

namespace App\Contracts\Notifications;

interface SmsChannel
{
    /**
     * Send a plain-text SMS to a phone number. Implementations swap the
     * default log-only driver for a real provider (Twilio, Vonage, Africa's
     * Talking, ...) by binding their own implementation in a service
     * provider — the rest of the app never depends on a specific provider.
     */
    public function send(string $to, string $message): void;
}
