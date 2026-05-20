<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Summary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NlpLearningDatasetService
{
    private const DATASET_PATH = 'nlp_learning/legal_summary_dataset.jsonl';
    private const INDEX_PATH = 'nlp_learning/legal_summary_dataset.index.json';

    public function __construct(
        private PythonNlpBridgeService $pythonNlp,
        private NlpAdaptiveLearningService $adaptiveLearning
    ) {}

    public function record(Document $document, Summary $summary, array $result): void
    {
        $sourceText = trim((string) $document->extracted_text);
        $targetSummary = $this->targetSummary($summary, $result);

        if ($sourceText === '' || $targetSummary === '') {
            return;
        }

        $exampleHash = hash('sha256', $document->id . '|' . $summary->id . '|' . $sourceText . '|' . $targetSummary);

        try {
            if (!$this->rememberHash($exampleHash)) {
                return;
            }

            $example = [
                'id' => $exampleHash,
                'document_id' => $document->id,
                'summary_id' => $summary->id,
                'summary_type' => $summary->summary_type ?: ($document->summary_type ?: 'general_user'),
                'document_name' => $document->original_name,
                'document_type' => $summary->document_type,
                'court' => $summary->court,
                'judge' => $summary->judge,
                'case_number' => $summary->case_number,
                'language' => $summary->nlp_language ?: 'en',
                'ai_provider' => $summary->ai_provider ?: 'nlp_local',
                'input_text' => $sourceText,
                'target_summary' => $targetSummary,
                'summary_fields' => [
                    'executive_summary' => $summary->executive_summary,
                    'professional_summary' => $summary->professional_summary,
                    'citizen_summary' => $summary->citizen_summary,
                    'key_findings' => $summary->key_findings,
                    'outcome' => $summary->outcome,
                    'practical_implications' => $summary->practical_implications,
                ],
                'labels' => [
                    'parties' => $this->jsonArray($summary->parties),
                    'key_obligations' => $this->jsonArray($summary->key_obligations),
                    'legal_categories' => $this->jsonArray($summary->nlp_legal_categories),
                    'keywords' => $this->jsonArray($summary->nlp_keywords),
                ],
                'created_at' => now()->toIso8601String(),
            ];

            $this->adaptiveLearning->learn($example);
            Storage::disk('local')->makeDirectory('nlp_learning');
            Storage::disk('local')->append(self::DATASET_PATH, json_encode($example, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->pythonNlp->learn($example);
        } catch (\Throwable $e) {
            Log::warning('JustConnect: could not record NLP learning example.', [
                'document_id' => $document->id,
                'summary_id' => $summary->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function totalRows(): int
    {
        return $this->datasetQuery()->count();
    }

    public function tableRows(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));

        return $this->datasetQuery()
            ->latest('summaries.created_at')
            ->limit($limit)
            ->get()
            ->map(fn(Summary $summary) => $this->datasetRow($summary))
            ->filter()
            ->values()
            ->all();
    }

    public function writeCsv($handle, int $limit = 10000): int
    {
        $limit = max(1, min($limit, 50000));
        $written = 0;

        fputcsv($handle, [
            'dataset_id',
            'document_id',
            'summary_id',
            'summary_type',
            'document_name',
            'document_type',
            'court',
            'judge',
            'case_number',
            'language',
            'ai_provider',
            'input_text',
            'output_text',
            'created_at',
        ]);

        $this->datasetQuery()
            ->orderBy('summaries.id')
            ->chunk(200, function ($summaries) use ($handle, $limit, &$written): bool {
                foreach ($summaries as $summary) {
                    $row = $this->datasetRow($summary);
                    if (!$row) {
                        continue;
                    }

                    fputcsv($handle, [
                        $row['dataset_id'],
                        $row['document_id'],
                        $row['summary_id'],
                        $row['summary_type'],
                        $row['document_name'],
                        $row['document_type'],
                        $row['court'],
                        $row['judge'],
                        $row['case_number'],
                        $row['language'],
                        $row['ai_provider'],
                        $row['input_text'],
                        $row['output_text'],
                        $row['created_at'],
                    ]);

                    $written++;
                    if ($written >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        return $written;
    }

    private function datasetQuery()
    {
        return Summary::with(['document', 'user'])
            ->whereHas('document', function ($query): void {
                $query->whereNotNull('extracted_text')
                    ->where('extracted_text', '!=', '');
            })
            ->where(function ($query): void {
                $query->whereNotNull('executive_summary')
                    ->orWhereNotNull('professional_summary')
                    ->orWhereNotNull('citizen_summary');
            });
    }

    private function datasetRow(Summary $summary): ?array
    {
        $document = $summary->document;
        $inputText = trim((string) ($document?->extracted_text ?? ''));
        $outputText = $this->targetSummary($summary, []);

        if ($inputText === '' || $outputText === '') {
            return null;
        }

        $datasetId = hash('sha256', $document?->id . '|' . $summary->id . '|' . $inputText . '|' . $outputText);

        return [
            'dataset_id' => $datasetId,
            'document_id' => $document?->id,
            'summary_id' => $summary->id,
            'summary_type' => $summary->summary_type ?: ($document?->summary_type ?: 'general_user'),
            'document_name' => $document?->original_name ?? 'Document #' . $document?->id,
            'document_type' => $summary->document_type ?: 'Legal Document',
            'court' => $summary->court,
            'judge' => $summary->judge,
            'case_number' => $summary->case_number,
            'language' => $summary->nlp_language ?: 'en',
            'ai_provider' => $summary->ai_provider ?: 'nlp_local',
            'input_text' => $inputText,
            'output_text' => $outputText,
            'input_preview' => $this->excerpt($inputText, 280),
            'output_preview' => $this->excerpt($outputText, 280),
            'created_at' => $summary->created_at?->format('d M Y H:i') ?? '',
        ];
    }

    private function targetSummary(Summary $summary, array $result): string
    {
        $summaryType = $summary->summary_type ?: ($result['summary_type'] ?? 'general_user');
        if ($summaryType === 'general_user') {
            return trim((string) ($summary->citizen_summary ?: $summary->executive_summary));
        }

        return trim((string) ($summary->professional_summary ?: $summary->executive_summary));
    }

    private function rememberHash(string $hash): bool
    {
        $index = [];
        if (Storage::disk('local')->exists(self::INDEX_PATH)) {
            $decoded = json_decode((string) Storage::disk('local')->get(self::INDEX_PATH), true);
            $index = is_array($decoded) ? $decoded : [];
        }

        if (isset($index[$hash])) {
            return false;
        }

        $index[$hash] = now()->toIso8601String();
        Storage::disk('local')->makeDirectory('nlp_learning');
        Storage::disk('local')->put(self::INDEX_PATH, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return true;
    }

    private function jsonArray(?string $value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function excerpt(string $text, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1), " \t\n\r\0\x0B,.;:-") . '...';
    }
}
