<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly ?Company $company;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
    ) {
        $this->company = Company::query()->first();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title,
            replyTo: $this->company?->email_reply_to ? [$this->company->email_reply_to] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification', with: [
            'title' => $this->title,
            'body' => $this->body,
            'company' => $this->company,
        ]);
    }
}
