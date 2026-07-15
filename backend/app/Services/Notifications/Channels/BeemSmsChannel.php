<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\SmsChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real SMS driver via Beem Africa (https://beem.africa) — a bulk-SMS
 * provider covering Tanzania/East Africa, matching this app's default
 * TZS/Dar-es-Salaam tenant profile. Swapped in for LogSmsChannel by
 * AppServiceProvider whenever BEEM_API_KEY is configured; falls back to
 * the log driver otherwise so local/test environments need no account.
 */
class BeemSmsChannel implements SmsChannel
{
    private const ENDPOINT = 'https://apisms.beem.africa/v1/send';

    public function send(string $to, string $message): void
    {
        $response = Http::withBasicAuth(
            config('services.beem.api_key'),
            config('services.beem.secret_key'),
        )->post(self::ENDPOINT, [
            'source_addr' => config('services.beem.sender_id'),
            'encoding' => 0,
            'message' => $message,
            'recipients' => [
                ['recipient_id' => 1, 'dest_addr' => $this->normalize($to)],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Beem SMS send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    /**
     * Beem expects digits only, country code without a leading '+'
     * (e.g. "255712345678") — strip everything else so a number entered
     * as "+255 712 345 678" or "0712345678" still resolves sensibly.
     */
    private function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        }

        return $digits;
    }
}
