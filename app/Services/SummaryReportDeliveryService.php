<?php

namespace App\Services;

use App\Mail\SummaryReportMail;
use App\Models\Document;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SummaryReportDeliveryService
{
    public function __construct(
        private SummaryReportPdfService $pdf
    ) {}

    public function deliver(Summary $summary, Document $document, User $user): bool
    {
        $path = $this->pdf->generate($summary, $document);

        Summary::query()
            ->whereKey($summary->id)
            ->update(['pdf_path' => $path]);

        $summary->setAttribute('pdf_path', $path);
        $summary->syncOriginalAttribute('pdf_path');

        if (!$this->mailIsConfigured()) {
            Log::warning('JustConnect: report email skipped because mail delivery is not configured.', [
                'summary_id' => $summary->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return false;
        }

        Mail::to($user->email)->send(new SummaryReportMail($user, $summary, $document, Storage::disk('local')->path($path)));

        return true;
    }

    private function mailIsConfigured(): bool
    {
        $defaultMailer = config('mail.default');
        $transport = config("mail.mailers.{$defaultMailer}.transport");

        return !in_array($defaultMailer, ['array', 'log'], true)
            && !in_array($transport, ['array', 'log'], true);
    }
}
