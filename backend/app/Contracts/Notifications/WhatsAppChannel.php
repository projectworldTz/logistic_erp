<?php

namespace App\Contracts\Notifications;

interface WhatsAppChannel
{
    /**
     * Send a WhatsApp message to a phone number. Implementations swap the
     * default log-only driver for a real provider (Meta Cloud API, Twilio
     * WhatsApp, ...) by binding their own implementation — the rest of the
     * app never depends on a specific provider.
     */
    public function send(string $to, string $message): void;
}
