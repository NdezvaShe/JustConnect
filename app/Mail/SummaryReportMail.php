<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SummaryReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public Summary $summary,
        public Document $document,
        private string $pdfAbsolutePath
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your JustConnect PDF report is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.summary-report',
            with: [
                'user' => $this->user,
                'summary' => $this->summary,
                'document' => $this->document,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfAbsolutePath)
                ->as('JustConnect_Report_' . $this->summary->id . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
