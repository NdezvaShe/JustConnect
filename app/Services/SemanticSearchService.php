<?php

namespace App\Services;

class SemanticSearchService
{
    public function __construct(
        private readonly LegalInsightService $insights
    ) {
    }

    public function rank(string $query, array $summaries): array
    {
        $query = trim($query);
        if ($query === '') {
            return $summaries;
        }

        $ranked = [];
        foreach ($summaries as $summary) {
            $score = $this->insights->scoreSearch($query, $summary);
            $textScore = $this->textAffinity($query, $summary);
            $combined = round(($score * 0.7) + ($textScore * 0.3), 4);

            if ($combined < 0.08 && $textScore <= 0.0) {
                continue;
            }

            $summary['search_score'] = $combined;
            $ranked[] = $summary;
        }

        usort($ranked, static fn (array $a, array $b): int => ($b['search_score'] <=> $a['search_score']));

        return $ranked;
    }

    private function textAffinity(string $query, array $summary): float
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $summary['document_name'] ?? '',
            $summary['document_type'] ?? '',
            $summary['professional_summary'] ?? '',
            $summary['citizen_summary'] ?? '',
            implode(' ', $summary['entities_involved'] ?? []),
            implode(' ', $summary['semantic_profile']['terms'] ?? []),
            implode(' ', $summary['structured_panels']['key_legal_issues'] ?? []),
        ])));

        $tokens = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return 0.0;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 3) {
                continue;
            }

            if (str_contains($haystack, $token)) {
                $hits++;
            }
        }

        return $hits / max(1, count($tokens));
    }
}
