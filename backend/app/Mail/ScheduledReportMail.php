<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly ?Company $company;

    public function __construct(
        public readonly string $reportName,
        public readonly string $module,
        public readonly string $fileContent,
        public readonly string $fileName,
        public readonly string $mimeType,
    ) {
        $this->company = Company::query()->first();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Scheduled Report: {$this->reportName}",
            replyTo: $this->company?->email_reply_to ? [$this->company->email_reply_to] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification', with: [
            'title' => $this->reportName,
            'body' => "Attached is your scheduled {$this->module} report.",
            'company' => $this->company,
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->fileContent, $this->fileName)
                ->withMime($this->mimeType),
        ];
    }
}
