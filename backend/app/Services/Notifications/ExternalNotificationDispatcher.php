<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsChannel;
use App\Contracts\Notifications\WhatsAppChannel;
use App\Mail\GenericNotificationMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Fans an already-decided in-app notification out to whichever external
 * channels the tenant has enabled (Company::notify_*_enabled) and the
 * recipient has contact details for. Sent synchronously (not queued) so it
 * works out of the box without a queue worker running.
 */
class ExternalNotificationDispatcher
{
    public function __construct(
        private readonly SmsChannel $sms,
        private readonly WhatsAppChannel $whatsapp,
    ) {}

    public function dispatch(User $recipient, string $title, string $message): void
    {
        $company = Company::query()->first();

        if (! $company) {
            return;
        }

        if ($company->notify_email_enabled && $recipient->email) {
            Mail::to($recipient->email)->send(new GenericNotificationMail($title, $message));
        }

        if ($company->notify_sms_enabled && $recipient->phone) {
            $this->sms->send($recipient->phone, $message);
        }

        if ($company->notify_whatsapp_enabled && $recipient->phone) {
            $this->whatsapp->send($recipient->phone, $message);
        }
    }
}
