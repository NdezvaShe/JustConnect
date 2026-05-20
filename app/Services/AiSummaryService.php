<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiSummaryService — sends extracted legal text through the configured
 * NLP_BART analysis path, then merges the result with local NLP signals.
 */
class AiSummaryService
{
    public function __construct(
        private NlpService $nlp,
        private LegalInsightService $insights,
        private SummaryPromptTemplateService $prompts
    ) {}

    /**
     * Analyse document text. Uses preferred AI provider with NLP as fallback.
     */
    public function analyse(string $text, string $filename = '', string $summaryType = SummaryPromptTemplateService::GENERAL_USER): array
    {
        if (!in_array($summaryType, $this->prompts->validTypes(), true)) {
            $summaryType = SummaryPromptTemplateService::GENERAL_USER;
        }

        $provider = config('services.ai.preferred', 'nlp_local');

        $result = null;
        $startMs = (int) round(microtime(true) * 1000);

        // Always run NLP — it enriches the result regardless of AI provider
        $nlpResult = $this->nlp->analyse($text, $filename);
        $nlpResult['summary_type'] = $summaryType;
        $context = $this->insights->buildAiContext($text, $nlpResult);

        if ($provider === 'openai' && config('services.openai.key')) {
            $result = $this->callOpenAi($filename, $context['prompt_text'], $summaryType);
        } elseif ($provider === 'gemini' && config('services.gemini.key')) {
            $result = $this->callGemini($filename, $context['prompt_text'], $summaryType);
        }

        $endMs = (int) round(microtime(true) * 1000);

        if ($result) {
            // Merge AI JSON output with NLP metadata
            $merged = array_merge($nlpResult, $result, [
                'document_type'         => $nlpResult['document_type'],
                'case_number'           => $this->bestString($result['case_number'] ?? null, $nlpResult['case_number'] ?? null),
                'parties'               => $this->bestParties($result['parties'] ?? [], $nlpResult['parties'] ?? []),
                'date_of_document'      => $this->bestString($result['date_of_document'] ?? null, $nlpResult['date_of_document'] ?? null),
                'court'                 => $this->bestString($result['court'] ?? null, $nlpResult['court'] ?? null),
                'judge'                 => $this->bestString($result['judge'] ?? null, $nlpResult['judge'] ?? null),
                'clauses'               => $nlpResult['clauses'] ?? ($result['clauses'] ?? []),
                'structured_extraction' => $nlpResult['structured_extraction'] ?? ($result['structured_extraction'] ?? []),
                'nlp_entities'         => $nlpResult['nlp_entities'],
                'nlp_keywords'         => $nlpResult['nlp_keywords'],
                'nlp_sentiment'        => $nlpResult['nlp_sentiment'],
                'nlp_readability'      => $nlpResult['nlp_readability'],
                'nlp_language'         => $nlpResult['nlp_language'],
                'nlp_legal_categories' => $nlpResult['nlp_legal_categories'],
                'ai_provider'          => $provider,
                'processing_ms'        => $endMs - $startMs,
                'summary_type'         => $summaryType,
            ]);

            return $this->applySummaryMode($this->insights->enrich($text, $merged, $context), $summaryType);
        }

        // Pure NLP fallback
        Log::info('JustConnect: Using pure NLP (no AI provider configured or AI call failed).');
        $nlpResult['processing_ms'] = $endMs - $startMs;
        $nlpResult['summary_type'] = $summaryType;

        return $this->applySummaryMode($this->insights->enrich($text, $nlpResult, $context), $summaryType);
    }

    /* ──────────────────────────── OpenAI GPT-4o ─────────────────────── */

    private function callOpenAi(string $filename, string $contextText, string $summaryType): ?array
    {
        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => config('services.openai.model', 'gpt-4o'),
                    'max_tokens'  => 1200,
                    'temperature' => 0.2,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => $this->prompts->systemPrompt($summaryType),
                        ],
                        [
                            'role'    => 'user',
                            'content' => "Document: {$filename}\n\nGrounded excerpts and retrieval context:\n{$contextText}",
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI call failed: ' . $response->status() . ' ' . $response->body());
                return null;
            }

            $raw = $response->json('choices.0.message.content', '');
            return $this->parseJson($raw);
        } catch (\Throwable $e) {
            Log::error('OpenAI exception: ' . $e->getMessage());
            return null;
        }
    }

    /* ──────────────────────────── NLP_BART external provider ─────────── */

    private function callGemini(string $filename, string $contextText, string $summaryType): ?array
    {
        try {
            $model   = config('services.gemini.model', 'gemini-2.5-flash');
            $key     = config('services.gemini.key');

            $response = Http::timeout(45)
                ->withHeaders([
                    'x-goog-api-key' => (string) $key,
                ])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => $this->prompts->systemPrompt($summaryType),
                        ]],
                    ],
                    'contents' => [[
                        'parts' => [[
                            'text' => "Document: {$filename}\n\nGrounded excerpts and retrieval context:\n{$contextText}",
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 3072,
                        'thinkingConfig'  => [
                            // The external flash provider can consume most of the budget on thoughts.
                            // unless we disable thinking for strict JSON extraction.
                            'thinkingBudget' => 0,
                        ],
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $this->geminiResponseSchema(),
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('NLP_BART provider call failed.', [
                    'status' => $response->status(),
                    'model' => $model,
                    'body_preview' => $this->bodyPreview($response->body()),
                ]);
                return null;
            }

            $raw = $response->json('candidates.0.content.parts.0.text', '');
            $parsed = $this->parseJson($raw);

            return is_array($parsed) ? $this->normaliseAiPayload($parsed) : null;
        } catch (\Throwable $e) {
            Log::error('NLP_BART provider exception: ' . $e->getMessage());
            return null;
        }
    }

    /* ─────────────────────────── shared helpers ─────────────────────── */

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a specialist Zimbabwean legal document analyst using NLP techniques.
Analyse the document and return ONLY a valid JSON object with exactly these keys — no preamble, no markdown fences, no extra keys:
{
  "document_type":          "string",
  "case_number":            "string or null",
  "parties":                ["array of named people or organisations only"],
  "date_of_document":       "string or null",
  "court":                  "string or null",
  "judge":                  "string or null",
  "executive_summary":      "120-240 word professional legal summary grounded only in the provided excerpts",
  "professional_summary":   "same style as executive_summary, grounded only in the provided excerpts",
  "citizen_summary":        "120-220 word plain-English citizen summary with minimal jargon",
  "key_findings":           "string or null",
  "key_obligations":        ["array of strings"],
  "legal_principles":       "string or null",
  "outcome":                "string or null",
  "practical_implications": "string or null",
  "key_issues":             ["array of concise legal issues"],
  "cited_instruments":      ["array of cited laws, regulations, statutory instruments, rules, or codes only; exclude case names and party names"],
  "legal_references":       ["array of important sections or statutes"]
}
Prioritise these user-facing fields above all else: date_of_document, court, judge, parties, and executive_summary.
Keep both summary fields grounded in the retrieved excerpts and do not guess beyond what those excerpts support.
Write citizen_summary in simple English, explain the dispute or decision in everyday language, avoid unnecessary legal jargon, and do not copy long phrases directly from the document.
If a non-essential field is not clearly stated in the text, return null or an empty array instead of guessing.
Focus on Zimbabwean law: High Court Act, Labour Act, Companies Act, Land Acquisition Act, Constitution of Zimbabwe 2013.
PROMPT;
    }

    private function applySummaryMode(array $result, string $summaryType): array
    {
        $result['summary_type'] = $summaryType;
        $panels = is_array($result['structured_panels'] ?? null) ? $result['structured_panels'] : [];

        $panels['summary_type'] = $summaryType;
        $panels['summary_mode_label'] = $this->prompts->label($summaryType);

        if ($summaryType === SummaryPromptTemplateService::LEGAL_PROFESSIONAL) {
            $summarySupport = $this->summarySupport($result, $panels);
            $result['professional_summary'] = $this->targetSummary((string) ($result['professional_summary'] ?? ''), $summarySupport);
            $result['executive_summary'] = $this->targetSummary((string) (($result['executive_summary'] ?? '') ?: $result['professional_summary']), $summarySupport);
            $result['citizen_summary'] = $this->targetSummary((string) ($result['citizen_summary'] ?? $result['executive_summary']), $summarySupport);
            $result = $this->polishResultProse($result);
            if ($this->isLegislativeDocumentType($result['document_type'] ?? '')) {
                $panels['mode_sections'] = $this->legislativeProfessionalSections($result, $panels);
                $result['structured_panels'] = $panels;

                return $result;
            }
            $legalIssues = $this->compactList($result['key_issues'] ?? ($panels['key_legal_issues'] ?? []));
            $orders = $this->compactList($result['key_obligations'] ?? []);
            $authorities = $this->compactList($result['legal_references'] ?? ($panels['important_legal_references'] ?? []));
            $instruments = $this->compactList($panels['cited_instruments'] ?? ($result['cited_instruments'] ?? []));
            $panels['mode_sections'] = [
                'Summary' => $result['professional_summary'] ?? $result['executive_summary'] ?? null,
                'Document Type' => $result['document_type'] ?? null,
                'Citation / Court Details' => trim(implode(' | ', array_filter([
                    $result['case_number'] ?? null,
                    $result['court'] ?? null,
                    $result['judge'] ?? null,
                    $result['date_of_document'] ?? null,
                ]))) ?: null,
                'Facts' => $this->compactParagraph((string) (($result['key_findings'] ?? '') ?: ($result['professional_summary'] ?? $result['executive_summary'] ?? '')), 90),
                'Legal Issues' => $legalIssues,
                'Holding / Decision' => $this->compactParagraph((string) ($result['outcome'] ?? ''), 70),
                'Ratio Decidendi' => $this->compactParagraph((string) ($result['legal_principles'] ?? ''), 80),
                'Orders / Remedies' => $orders,
                'Authorities Cited' => $authorities,
                'Cited Instruments' => $instruments,
            ];
        } else {
            $summarySupport = $this->summarySupport($result, $panels);
            $result['citizen_summary'] = $this->targetSummary((string) ($result['citizen_summary'] ?? ''), $summarySupport);
            $result['executive_summary'] = $this->targetSummary((string) (($result['executive_summary'] ?? '') ?: $result['citizen_summary']), $summarySupport);
            $result['professional_summary'] = $this->targetSummary((string) ($result['professional_summary'] ?? $result['executive_summary']), $summarySupport);
            $result = $this->polishResultProse($result);
            if ($this->isLegislativeDocumentType($result['document_type'] ?? '')) {
                $panels['mode_sections'] = $this->legislativeGeneralSections($result, $panels);
                $result['structured_panels'] = $panels;

                return $result;
            }
            $documentOverview = $this->compactParagraph((string) (($result['key_findings'] ?? '') ?: ($result['citizen_summary'] ?? $result['executive_summary'] ?? '')), 150);
            $outcome = $this->bestOutcome($result);
            $implications = $this->compactParagraph((string) ($result['practical_implications'] ?? ''), 130);
            $panels['mode_sections'] = [
                'Summary' => $result['citizen_summary'] ?? $result['executive_summary'] ?? null,
                'Document Overview' => $documentOverview ?: ($result['citizen_summary'] ?? $result['executive_summary'] ?? null),
                'Main Issue' => $this->compactList($result['key_issues'] ?? ($panels['key_legal_issues'] ?? []), 4, 42),
                'Decision / Outcome' => $outcome,
                'What This Means' => $implications ?: null,
            ];
        }

        $result['structured_panels'] = $panels;

        return $result;
    }

    private function legislativeProfessionalSections(array $result, array $panels): array
    {
        $instruments = $this->compactList($panels['cited_instruments'] ?? ($result['cited_instruments'] ?? []));

        return [
            'Summary' => $result['professional_summary'] ?? $result['executive_summary'] ?? null,
            'Document Type' => $result['document_type'] ?? 'Legislative Instrument',
            'Legislative Subject' => $this->compactParagraph((string) (($result['key_findings'] ?? '') ?: ($result['executive_summary'] ?? '')), 90),
            'Operative Provisions' => $this->compactList($result['key_obligations'] ?? [], 6, 44),
            'Legal Effect' => $this->compactParagraph((string) ($result['outcome'] ?? ''), 90),
            'Compliance Implications' => $this->compactParagraph((string) ($result['practical_implications'] ?? ''), 90),
            'References' => $this->compactList($result['legal_references'] ?? ($panels['important_legal_references'] ?? []), 6, 44),
            'Cited Instruments' => $instruments,
        ];
    }

    private function legislativeGeneralSections(array $result, array $panels): array
    {
        return [
            'Summary' => $result['citizen_summary'] ?? $result['executive_summary'] ?? null,
            'Document Overview' => $this->compactParagraph((string) (($result['key_findings'] ?? '') ?: ($result['citizen_summary'] ?? $result['executive_summary'] ?? '')), 150),
            'Main Legal Area' => $this->compactList($result['key_issues'] ?? ($panels['key_legal_issues'] ?? []), 4, 42),
            'Legal Effect' => $this->compactParagraph((string) ($result['outcome'] ?? ''), 90),
            'What This Means' => $this->compactParagraph((string) ($result['practical_implications'] ?? ''), 130),
        ];
    }

    private function isLegislativeDocumentType(string $docType): bool
    {
        return in_array($docType, ['Act', 'Bill', 'Statutory Instrument'], true);
    }

    private function polishResultProse(array $result): array
    {
        foreach (['executive_summary', 'professional_summary', 'citizen_summary'] as $field) {
            $result[$field] = $this->polishParagraph((string) ($result[$field] ?? ''), 300);
        }

        foreach (['key_findings', 'legal_principles', 'outcome', 'practical_implications'] as $field) {
            if (!empty($result[$field])) {
                $result[$field] = $this->polishParagraph((string) $result[$field], 120, $field === 'outcome' ? 3 : 5);
            }
        }

        foreach (['key_issues', 'key_obligations', 'legal_references', 'cited_instruments'] as $field) {
            if (!empty($result[$field]) && is_array($result[$field])) {
                $result[$field] = $this->polishListItems($result[$field]);
            }
        }

        return $result;
    }

    private function polishParagraph(string $text, int $wordLimit, int $minimumWords = 6): string
    {
        $text = $this->normaliseProseText($text);
        if ($text === '') {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = [];
        $seen = [];

        foreach ($sentences as $sentence) {
            $sentence = $this->normaliseProseSentence((string) $sentence);
            if (!$this->isMeaningfulSentence($sentence, $minimumWords)) {
                continue;
            }

            $key = mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', ' ', $sentence));
            if (isset($seen[$key])) {
                continue;
            }

            $candidate = trim(implode(' ', [...$kept, $sentence]));
            if ($this->wordCount($candidate) > $wordLimit) {
                break;
            }

            $kept[] = $sentence;
            $seen[$key] = true;
        }

        return implode(' ', $kept) ?: $this->normaliseProseSentence($text);
    }

    private function polishListItems(array $items): array
    {
        $polished = [];
        foreach ($items as $item) {
            $text = is_array($item)
                ? (string) ($item['text'] ?? $item['title'] ?? $item['value'] ?? '')
                : (string) $item;
            $sentence = $this->normaliseProseSentence($text);
            if ($this->isMeaningfulSentence($sentence, 3)) {
                $polished[] = $sentence;
            }
        }

        return array_values(array_unique($polished));
    }

    private function normaliseProseText(string $text): string
    {
        $text = preg_replace('/\bPAGE\s+\d+\b/iu', ' ', $text);
        $text = preg_replace('/\s+([,.;:!?])/u', '$1', (string) $text);
        $text = preg_replace('/([,.;:!?])([^\s,.;:!?])/u', '$1 $2', (string) $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);

        return trim((string) $text);
    }

    private function normaliseProseSentence(string $sentence): string
    {
        $sentence = $this->normaliseProseText($sentence);
        $sentence = preg_replace(
            '/^\s*(?:Summary|Document Overview|What Happened|Facts|Legal Issues|Holding\s*\/\s*Decision|Decision\s*\/\s*Outcome|What This Means|Ratio Decidendi|Orders\s*\/\s*Remedies)\s*[:\-]\s*/iu',
            '',
            (string) $sentence
        );
        $sentence = trim((string) $sentence, " \t\n\r\0\x0B\"'`*_,-;:");

        if ($sentence === '') {
            return '';
        }

        $sentence = preg_replace('/\.{2,}$/u', '.', (string) $sentence);
        $sentence = preg_match('/[.!?]$/u', (string) $sentence) ? (string) $sentence : $sentence . '.';
        $first = mb_substr($sentence, 0, 1);

        return mb_strtoupper($first) . mb_substr($sentence, 1);
    }

    private function isMeaningfulSentence(string $sentence, int $minimumWords = 6): bool
    {
        $sentence = trim($sentence);
        if ($sentence === '' || preg_match('/^(?:none|n\/a|not stated|unknown|with costs|costs)\.?$/iu', $sentence)) {
            return false;
        }

        $words = preg_split('/\s+/u', trim($sentence), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < $minimumWords) {
            return false;
        }

        return preg_match('/\b(?:is|are|was|were|be|being|been|has|have|had|must|may|shall|will|can|could|should|would|concerns|states|sets|requires|allows|prohibits|creates|explains|shows|finds|found|held|ordered|dismissed|granted|refused|upheld|allowed|convicted|acquitted|sentenced|affects|means|applies|provides)\b/iu', $sentence) === 1;
    }

    private function limitWords(string $text, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= $limit) {
            return trim($text);
        }

        return implode(' ', array_slice($words, 0, $limit)) . '...';
    }

    private function compactParagraph(string $text, int $wordLimit): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $text = preg_replace(
            '/\b(?:Summary|Document Overview|What Happened|Document Type|Citation\s*\/\s*Court Details|Facts|Legal Issues|Holding\s*\/\s*Decision|Ratio Decidendi|Orders\s*\/\s*Remedies|Authorities Cited|Cited Instruments)\s*[:\-]\s*/iu',
            '',
            $text
        );

        return $this->limitWords((string) $text, $wordLimit);
    }

    private function compactList(mixed $value, int $itemLimit = 5, int $itemWordLimit = 18): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['text'] ?? $item['title'] ?? $item['value'] ?? '';
            }

            $text = $this->compactParagraph((string) $item, $itemWordLimit);
            if ($text !== '') {
                $items[] = $text;
            }

            if (count($items) >= $itemLimit) {
                break;
            }
        }

        return array_values(array_unique($items));
    }

    private function targetSummary(string $summary, array $support, int $minWords = 200, int $maxWords = 300): string
    {
        $summary = $this->compactParagraph($summary, $maxWords);
        $words = $this->wordCount($summary);
        if ($words >= $minWords) {
            return $summary;
        }

        foreach ($this->supportSentences($support) as $sentence) {
            if ($this->isRedundantSentence($sentence, $summary)) {
                continue;
            }

            $candidate = trim($summary . ' ' . $sentence);
            if ($this->wordCount($candidate) > $maxWords) {
                break;
            }

            $summary = $candidate;
            if ($this->wordCount($summary) >= $minWords) {
                break;
            }
        }

        return $this->compactParagraph($summary, $maxWords);
    }

    private function summarySupport(array $result, array $panels): array
    {
        return [
            $result['key_findings'] ?? '',
            $result['outcome'] ?? '',
            $result['legal_principles'] ?? '',
            $result['practical_implications'] ?? '',
            $result['key_issues'] ?? [],
            $result['key_obligations'] ?? [],
            $panels['key_legal_issues'] ?? [],
            $panels['important_legal_references'] ?? [],
            $panels['cited_instruments'] ?? [],
            $result['supporting_passages'] ?? [],
        ];
    }

    private function supportSentences(array $support): array
    {
        $text = $this->flattenSupport($support);
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $cleaned = [];

        foreach ($sentences as $sentence) {
            $sentence = $this->compactParagraph((string) $sentence, 42);
            if ($sentence !== '' && $this->wordCount($sentence) >= 6) {
                $cleaned[] = rtrim($sentence, '.') . '.';
            }
        }

        return array_values(array_unique($cleaned));
    }

    private function flattenSupport(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $parts[] = $this->flattenSupport($item['text'] ?? $item['finding'] ?? $item['quote'] ?? $item['title'] ?? $item['value'] ?? $item);
                } else {
                    $parts[] = $this->flattenSupport($item);
                }
            }

            return implode(' ', array_filter($parts));
        }

        return trim((string) $value);
    }

    private function isRedundantSentence(string $sentence, string $summary): bool
    {
        $sentence = mb_strtolower(trim($sentence));
        $summary = mb_strtolower(trim($summary));
        if ($sentence === '' || str_contains($summary, rtrim($sentence, '.'))) {
            return true;
        }

        $tokens = array_unique(array_filter(preg_split('/\W+/u', $sentence) ?: [], fn (string $token): bool => mb_strlen($token) >= 5));
        if (count($tokens) < 4) {
            return false;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (str_contains($summary, $token)) {
                $hits++;
            }
        }

        return ($hits / count($tokens)) >= 0.75;
    }

    private function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function bestString(mixed $preferred, mixed $fallback): ?string
    {
        $preferred = $this->nullableText($preferred);
        if ($preferred !== null) {
            return $preferred;
        }

        return $this->nullableText($fallback);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (in_array(mb_strtolower($value), ['null', 'none', 'n/a', 'unknown', 'not stated', 'not specified'], true)) {
            return null;
        }

        return $value;
    }

    private function bestParties(mixed $aiParties, mixed $localParties): array
    {
        $ai = $this->stringList(is_array($aiParties) ? $aiParties : []);
        $local = $this->stringList(is_array($localParties) ? $localParties : []);

        $cleanAi = array_values(array_filter($ai, fn (string $party): bool => !$this->looksLikeCitedAuthority($party)));
        if (count($cleanAi) >= 2) {
            return array_slice($cleanAi, 0, 8);
        }

        $cleanLocal = array_values(array_filter($local, fn (string $party): bool => !$this->looksLikeCitedAuthority($party)));
        if (count($cleanLocal) >= 2) {
            return array_slice($cleanLocal, 0, 8);
        }

        return array_slice($cleanAi ?: $cleanLocal ?: $ai ?: $local, 0, 8);
    }

    private function looksLikeCitedAuthority(string $value): bool
    {
        return preg_match('/\b(?:supreme court of appeal|court of appeal|law reports?|\d{4}\s+z\w+|sa\s+\d+|pty\s+ltd\s+and\s+[a-z]+\s+\d{4})\b/iu', $value) === 1;
    }

    private function bestOutcome(array $result): ?string
    {
        $outcome = $this->compactParagraph((string) ($result['outcome'] ?? ''), 95);
        if ($this->isUsefulOutcome($outcome)) {
            return $outcome;
        }

        foreach (($result['result_cards'] ?? []) as $card) {
            if (!is_array($card) || mb_strtolower((string) ($card['title'] ?? '')) !== 'case result') {
                continue;
            }

            $items = $this->compactList($card['items'] ?? [], 2, 30);
            if ($items !== []) {
                return implode('; ', $items) . '.';
            }
        }

        foreach ([$result['citizen_summary'] ?? '', $result['executive_summary'] ?? '', $result['professional_summary'] ?? ''] as $summary) {
            $summary = (string) $summary;
            foreach (preg_split('/(?<=[.!?])\s+/u', $summary, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $sentence) {
                $sentence = $this->compactParagraph($sentence, 45);
                if ($this->isUsefulOutcome($sentence)) {
                    return $sentence;
                }
            }
        }

        return null;
    }

    private function isUsefulOutcome(string $outcome): bool
    {
        $outcome = trim($outcome);
        if ($outcome === '') {
            return false;
        }

        $wordCount = count(preg_split('/\s+/u', $outcome, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($wordCount < 4 || preg_match('/^(?:with\s+costs|costs)$/iu', rtrim($outcome, '.'))) {
            return false;
        }

        return preg_match('/\b(?:dismissed|granted|allowed|upheld|set aside|refused|convicted|acquitted|sentenced|ordered|award|appeal|application|judgment)\b/iu', $outcome) === 1;
    }

    private function parseJson(string $raw): ?array
    {
        $cleaned = preg_replace('/^```json\s*/m', '', $raw);
        $cleaned = preg_replace('/^```\s*/m', '', $cleaned);
        $cleaned = preg_replace('/```$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        try {
            $data = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data) && isset($data['document_type'])) {
                return $data;
            }
        } catch (\JsonException $e) {
            // Try to find JSON object inside the string
            if (preg_match('/\{[\s\S]+\}/m', $cleaned, $m)) {
                try {
                    $data = json_decode($m[0], true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($data)) return $data;
                } catch (\JsonException) {}
            }
        }
        return null;
    }

    private function geminiResponseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'document_type' => ['type' => 'STRING'],
                'case_number' => ['type' => 'STRING'],
                'parties' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'date_of_document' => ['type' => 'STRING'],
                'court' => ['type' => 'STRING'],
                'judge' => ['type' => 'STRING'],
                'executive_summary' => ['type' => 'STRING'],
                'professional_summary' => ['type' => 'STRING'],
                'citizen_summary' => ['type' => 'STRING'],
                'key_findings' => ['type' => 'STRING'],
                'key_obligations' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'legal_principles' => ['type' => 'STRING'],
                'outcome' => ['type' => 'STRING'],
                'practical_implications' => ['type' => 'STRING'],
                'key_issues' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'cited_instruments' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'legal_references' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
            ],
            'required' => [
                'document_type',
                'case_number',
                'parties',
                'date_of_document',
                'court',
                'judge',
                'executive_summary',
                'professional_summary',
                'citizen_summary',
                'key_findings',
                'key_obligations',
                'legal_principles',
                'outcome',
                'practical_implications',
                'key_issues',
                'cited_instruments',
                'legal_references',
            ],
        ];
    }

    private function normaliseAiPayload(array $payload): array
    {
        foreach (['case_number', 'date_of_document', 'court', 'judge', 'key_findings', 'legal_principles', 'outcome', 'practical_implications'] as $field) {
            $payload[$field] = $this->nullableString($payload[$field] ?? null);
        }

        foreach (['document_type', 'executive_summary', 'professional_summary', 'citizen_summary'] as $field) {
            $payload[$field] = $this->requiredString($payload[$field] ?? '');
        }

        foreach (['parties', 'key_obligations', 'key_issues', 'cited_instruments', 'legal_references'] as $field) {
            $payload[$field] = $this->stringList($payload[$field] ?? []);
        }

        return $payload;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalised = mb_strtolower($value);
        if (in_array($normalised, ['null', 'none', 'n/a', 'unknown', 'not stated', 'not specified'], true)) {
            return null;
        }

        return $value;
    }

    private function requiredString(mixed $value): string
    {
        return trim((string) $value);
    }

    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $value
        ), static fn (string $item): bool => $item !== ''));

        return array_values(array_unique($items));
    }

    private function bodyPreview(string $body, int $limit = 800): string
    {
        return mb_substr(trim($body), 0, $limit);
    }
}
