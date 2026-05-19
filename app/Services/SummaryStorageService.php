<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Summary;
use Illuminate\Support\Facades\Schema;

class SummaryStorageService
{
    private ?array $summaryColumns = null;

    public function __construct(
        private NlpLearningDatasetService $learningDataset
    ) {}

    public function store(Document $document, array $result): Summary
    {
        $payload = [
            'summary_type' => $result['summary_type'] ?? ($document->summary_type ?? 'general_user'),
            'document_type' => $result['document_type'] ?? null,
            'case_number' => $result['case_number'] ?? null,
            'parties' => json_encode($result['parties'] ?? []),
            'date_of_document' => $result['date_of_document'] ?? null,
            'court' => $result['court'] ?? null,
            'judge' => $result['judge'] ?? null,
            'executive_summary' => $result['executive_summary'] ?? ($result['professional_summary'] ?? null),
            'professional_summary' => $result['professional_summary'] ?? ($result['executive_summary'] ?? null),
            'citizen_summary' => $result['citizen_summary'] ?? null,
            'key_findings' => $result['key_findings'] ?? null,
            'key_obligations' => json_encode($result['key_obligations'] ?? []),
            'legal_principles' => $result['legal_principles'] ?? null,
            'outcome' => $result['outcome'] ?? null,
            'practical_implications' => $result['practical_implications'] ?? null,
            'result_cards' => json_encode($result['result_cards'] ?? []),
            'structured_panels' => json_encode($result['structured_panels'] ?? []),
            'supporting_passages' => json_encode($result['supporting_passages'] ?? []),
            'source_map' => json_encode($result['source_map'] ?? []),
            'semantic_profile' => json_encode($result['semantic_profile'] ?? []),
            'nlp_entities' => json_encode($result['nlp_entities'] ?? []),
            'nlp_keywords' => json_encode($result['nlp_keywords'] ?? []),
            'nlp_sentiment' => $result['nlp_sentiment'] ?? null,
            'nlp_readability' => $result['nlp_readability'] ?? null,
            'nlp_language' => $result['nlp_language'] ?? 'en',
            'nlp_legal_categories' => json_encode($result['nlp_legal_categories'] ?? []),
            'ai_provider' => $result['ai_provider'] ?? 'nlp_local',
            'processing_ms' => $result['processing_ms'] ?? null,
        ];

        $summary = Summary::updateOrCreate(
            ['document_id' => $document->id, 'user_id' => $document->user_id],
            $this->filterExistingColumns($payload)
        );

        $freshSummary = $summary->fresh() ?? $summary;
        $this->learningDataset->record($document, $freshSummary, $result);

        // Return a clean model state from the database so later update() calls
        // do not try to re-persist transient in-memory attributes.
        return $freshSummary;
    }

    private function filterExistingColumns(array $payload): array
    {
        $columns = $this->summaryColumns();

        return array_filter(
            $payload,
            static fn (string $column): bool => in_array($column, $columns, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function summaryColumns(): array
    {
        if ($this->summaryColumns !== null) {
            return $this->summaryColumns;
        }

        $this->summaryColumns = Schema::getColumnListing('summaries');

        return $this->summaryColumns;
    }
}
