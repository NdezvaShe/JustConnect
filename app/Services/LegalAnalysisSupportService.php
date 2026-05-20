<?php

namespace App\Services;

class LegalAnalysisSupportService
{
    public function build(string $text, array $summary): array
    {
        $pages = $this->pages($text);
        $sentences = $this->sentences($pages);
        $entities = $this->normaliseArray($summary['nlp_entities'] ?? []);
        $obligations = $this->normaliseArray($summary['key_obligations'] ?? []);
        $caseNumber = $summary['case_number'] ?? null;
        $docType = (string) ($summary['document_type'] ?? 'Legal Document');
        $outcome = (string) ($summary['outcome'] ?? '');

        return [
            'evidence' => [
                'findings' => $this->findingEvidence((string) ($summary['key_findings'] ?? ''), $sentences),
                'obligations' => $this->obligationEvidence($obligations, $sentences),
                'outcome' => $this->outcomeEvidence($outcome, $sentences),
                'entities' => $this->entityEvidence($entities, $sentences),
            ],
            'legal_risk' => [
                'urgency' => $this->urgency($summary, $sentences),
                'missing_information' => $this->missingInformation($summary, $entities, $caseNumber),
                'deadlines' => $this->deadlines($sentences),
                'risky_clauses' => $this->riskyClauses($sentences),
                'recommended_actions' => $this->recommendedActions($docType, $summary, $sentences),
            ],
        ];
    }

    private function pages(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        preg_match_all('/(?:^|\n)\s*[—-]{2,}\s*PAGE\s+(\d+)\s*[—-]{2,}\s*\n?/iu', $text, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[0])) {
            return [['page' => 1, 'text' => $text]];
        }

        $pages = [];
        $markers = $matches[0];
        $numbers = $matches[1];

        foreach ($markers as $index => $marker) {
            $pageNumber = (int) $numbers[$index][0];
            $start = $marker[1] + strlen($marker[0]);
            $end = $markers[$index + 1][1] ?? strlen($text);
            $pages[] = [
                'page' => $pageNumber,
                'text' => trim(substr($text, $start, $end - $start)),
            ];
        }

        return array_values(array_filter($pages, fn(array $page) => $page['text'] !== ''));
    }

    private function sentences(array $pages): array
    {
        $results = [];

        foreach ($pages as $page) {
            $chunks = preg_split('/(?<=[.!?])\s+|\n{2,}/u', preg_replace('/\s+/u', ' ', $page['text']) ?: '', -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($chunks as $chunk) {
                $sentence = trim((string) $chunk);
                if (mb_strlen($sentence) < 20) {
                    continue;
                }

                $results[] = [
                    'page' => $page['page'],
                    'text' => $sentence,
                    'tokens' => $this->tokens($sentence),
                ];
            }
        }

        return $results;
    }

    private function findingEvidence(string $keyFindings, array $sentences): array
    {
        $findings = preg_split('/(?<=[.!?])\s+/u', trim($keyFindings), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $evidence = [];

        foreach ($findings as $finding) {
            $match = $this->bestSentenceMatch($finding, $sentences);
            if ($match === null) {
                continue;
            }

            $evidence[] = [
                'finding' => $finding,
                'quote' => $match['text'],
                'page' => $match['page'],
                'reason' => 'Selected because the source sentence shares the strongest legal-term overlap with this finding.',
            ];
        }

        return $evidence;
    }

    private function obligationEvidence(array $obligations, array $sentences): array
    {
        $results = [];

        foreach ($obligations as $item) {
            $text = is_array($item) ? (string) ($item['text'] ?? '') : (string) $item;
            if ($text === '') {
                continue;
            }

            $match = $this->bestSentenceMatch($text, $sentences, ['shall', 'must', 'required', 'undertake', 'entitled', 'prohibited']);
            $results[] = [
                'text' => $text,
                'quote' => $match['text'] ?? 'No direct clause match found in the extracted text.',
                'page' => $match['page'] ?? null,
                'reason' => $match
                    ? 'Contains directive or duty language that supports this obligation.'
                    : 'This obligation is a safety fallback and should be verified against the full source document.',
            ];
        }

        return $results;
    }

    private function outcomeEvidence(string $outcome, array $sentences): array
    {
        if ($outcome === '') {
            return [];
        }

        $match = $this->bestSentenceMatch($outcome, $sentences, ['ordered', 'dismissed', 'granted', 'upheld', 'set aside', 'refused', 'therefore', 'accordingly']);

        return [[
            'text' => $outcome,
            'quote' => $match['text'] ?? $outcome,
            'page' => $match['page'] ?? null,
            'reason' => $match
                ? 'Matched against dispositive language that usually signals the operative outcome.'
                : 'Outcome was inferred from the summary text and should be confirmed in the dispositive section.',
        ]];
    }

    private function entityEvidence(array $entities, array $sentences): array
    {
        $results = [];

        foreach ($entities as $group => $values) {
            if (!is_array($values)) {
                continue;
            }

            $normalisedValues = $this->flattenEntityValues($values);
            if (empty($normalisedValues)) {
                continue;
            }

            $results[$group] = [];

            foreach ($normalisedValues as $value) {
                $match = $this->firstSentenceContaining($value, $sentences);
                $results[$group][] = [
                    'value' => $value,
                    'quote' => $match['text'] ?? null,
                    'page' => $match['page'] ?? null,
                    'reason' => $this->entityReason((string) $group),
                ];
            }
        }

        return $results;
    }

    private function urgency(array $summary, array $sentences): string
    {
        $deadlineCount = count($this->deadlines($sentences));
        $riskyClauses = count($this->riskyClauses($sentences));
        $outcome = mb_strtolower((string) ($summary['outcome'] ?? ''));
        $docType = mb_strtolower((string) ($summary['document_type'] ?? ''));

        if ($deadlineCount > 0 || preg_match('/\b(with costs|dismissed|granted|refused|set aside|appeal)\b/u', $outcome)) {
            return 'high';
        }

        if ($riskyClauses >= 2 || preg_match('/\b(contract|agreement|lease|employment|loan)\b/u', $docType)) {
            return 'medium';
        }

        return 'low';
    }

    private function missingInformation(array $summary, array $entities, ?string $caseNumber): array
    {
        $missing = [];
        $docType = (string) ($summary['document_type'] ?? '');

        if ($caseNumber === null && str_contains($docType, 'Judgment')) {
            $missing[] = 'Case number or neutral citation is not confidently extracted.';
        }
        if (empty($summary['date_of_document'])) {
            $missing[] = 'Document date is missing or could not be matched to the extracted text.';
        }
        if (empty($summary['court']) && str_contains($docType, 'Judgment')) {
            $missing[] = 'Court or jurisdiction is not clearly identified.';
        }
        if (empty($summary['parties']) && str_contains($docType, 'Judgment')) {
            $missing[] = 'Parties were not clearly identified from the extracted text.';
        }
        if (($summary['outcome'] ?? '') === 'Outcome not expressly stated in the extracted text. Please review the full document for the court order or dispositive clause.') {
            $missing[] = 'Dispositive outcome is not expressly stated in the extracted text.';
        }
        if (empty($entities['dates'] ?? [])) {
            $missing[] = 'No date entities were confidently extracted.';
        }

        return $missing;
    }

    private function deadlines(array $sentences): array
    {
        $results = [];
        $pattern = '/\b(within\s+\d+\s+(?:day|days|month|months|year|years)|on or before\s+[^.]+|not later than\s+[^.]+|deadline[^.]*|before\s+\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}|before\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})\b/iu';

        foreach ($sentences as $sentence) {
            if (preg_match($pattern, $sentence['text'])) {
                $results[] = [
                    'text' => $sentence['text'],
                    'page' => $sentence['page'],
                ];
            }
        }

        return array_slice($results, 0, 4);
    }

    private function riskyClauses(array $sentences): array
    {
        $results = [];
        $pattern = '/\b(indemnif|penalt|terminat|default|breach|interest|liabilit|costs|cancel|set aside|dismiss|security|confidential|exclusive jurisdiction)\w*/iu';

        foreach ($sentences as $sentence) {
            if (preg_match($pattern, $sentence['text'])) {
                $results[] = [
                    'text' => $sentence['text'],
                    'page' => $sentence['page'],
                ];
            }
        }

        return array_slice($results, 0, 5);
    }

    private function recommendedActions(string $docType, array $summary, array $sentences): array
    {
        $actions = [];

        foreach ($this->deadlines($sentences) as $deadline) {
            $actions[] = 'Review the quoted deadline on page ' . $deadline['page'] . ' and calendar it immediately.';
        }

        if (($summary['outcome'] ?? '') !== '' && str_contains(mb_strtolower((string) ($summary['document_type'] ?? '')), 'judgment')) {
            $actions[] = 'Verify the dispositive paragraph against the full judgment before relying on the outcome.';
        }

        if (preg_match('/\b(contract|agreement|lease|employment|loan)\b/iu', $docType)) {
            $actions[] = 'Review the highlighted obligations and risky clauses with the responsible party before execution or performance.';
        }

        if (preg_match('/\bbill|statute|act\b/iu', $docType)) {
            $actions[] = 'Check whether the legislative text is still current and whether any later amendment changed the quoted provisions.';
        }

        if (empty($actions)) {
            $actions[] = 'Confirm the extracted evidence against the original document before sharing or acting on the summary.';
        }

        return array_values(array_unique($actions));
    }

    private function bestSentenceMatch(string $text, array $sentences, array $preferredTerms = []): ?array
    {
        $needleTokens = $this->tokens($text);
        if (empty($needleTokens)) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($sentences as $sentence) {
            $overlap = count(array_intersect($needleTokens, $sentence['tokens']));
            if ($overlap === 0) {
                continue;
            }

            $score = $overlap / max(1, count($needleTokens));

            foreach ($preferredTerms as $term) {
                if (str_contains(mb_strtolower($sentence['text']), mb_strtolower($term))) {
                    $score += 0.2;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $sentence;
            }
        }

        return $best;
    }

    private function firstSentenceContaining(string $value, array $sentences): ?array
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return null;
        }

        foreach ($sentences as $sentence) {
            if (str_contains(mb_strtolower($sentence['text']), $needle)) {
                return $sentence;
            }
        }

        return $this->bestSentenceMatch($value, $sentences);
    }

    private function entityReason(string $group): string
    {
        return match ($group) {
            'persons' => 'Extracted because the sentence contains a person-name pattern or judicial title.',
            'organisations' => 'Extracted because the sentence contains an institutional or corporate reference.',
            'courts' => 'Extracted because the sentence names a court or jurisdiction.',
            'dates' => 'Extracted because the sentence contains a document or procedural date.',
            'amounts' => 'Extracted because the sentence contains a monetary figure.',
            default => 'Extracted from the closest matching source sentence.',
        };
    }

    private function tokens(string $text): array
    {
        preg_match_all("/\p{L}[\p{L}\p{Mn}'-]{1,}/u", mb_strtolower($text), $matches);

        return array_values(array_unique(array_filter($matches[0] ?? [], fn(string $token) => mb_strlen($token) >= 4)));
    }

    private function normaliseArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function flattenEntityValues(array $values): array
    {
        $flattened = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $flattened = array_merge($flattened, $this->flattenEntityValues($value));
                continue;
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $flattened[] = $text;
        }

        $seen = [];
        $unique = [];

        foreach ($flattened as $value) {
            $key = mb_strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $value;
        }

        return $unique;
    }
}
