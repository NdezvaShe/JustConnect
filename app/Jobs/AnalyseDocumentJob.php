<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AiSummaryService;
use App\Services\AnalysisProgressService;
use App\Services\SummaryReportDeliveryService;
use App\Services\SummaryStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyseDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(private readonly int $documentId)
    {
    }

    public function handle(
        AiSummaryService $ai,
        AnalysisProgressService $progress,
        SummaryStorageService $storage,
        SummaryReportDeliveryService $reports
    ): void {
        $document = Document::with('user')->findOrFail($this->documentId);

        $progress->update($document->id, 'processing', 15, 'Preparing extracted text...');

        if (!$document->extracted_text || mb_strlen(trim($document->extracted_text)) < 20) {
            $document->update(['status' => 'failed']);
            $progress->update($document->id, 'failed', 100, 'Analysis failed.', 'No extracted text was available for analysis.');

            return;
        }

        $progress->update($document->id, 'processing', 40, 'Running legal NLP and summary analysis...');
        $result = $ai->analyse($document->extracted_text, $document->original_name, $document->summary_type ?? 'general_user');

        $progress->update($document->id, 'processing', 80, 'Saving concise summary...');
        $summary = $storage->store($document, $result);

        $progress->update($document->id, 'processing', 90, 'Generating PDF report...');

        if ($document->user?->email) {
            try {
                $progress->update($document->id, 'processing', 95, 'Sending report to your email...');
                $reports->deliver($summary, $document, $document->user);
            } catch (\Throwable $e) {
                Log::warning('JustConnect: report email delivery failed.', [
                    'document_id' => $document->id,
                    'summary_id' => $summary->id,
                    'user_id' => $document->user?->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $document->update(['status' => 'done']);
        $progress->update($document->id, 'done', 100, 'Analysis complete.');

        Log::info('JustConnect: analysis job completed.', [
            'document_id' => $document->id,
            'summary_id' => $summary->id,
            'provider' => $result['ai_provider'] ?? 'nlp_local',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $document = Document::find($this->documentId);
        if ($document) {
            $document->update(['status' => 'failed']);
            app(AnalysisProgressService::class)->update(
                $document->id,
                'failed',
                100,
                'Analysis failed.',
                'The analysis queue job failed. Please retry once the queue worker is running.'
            );
        }

        Log::error('JustConnect: analysis job failed.', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
