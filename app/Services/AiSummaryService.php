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
                'case_number'           => $nlpResult['case_number'] ?? ($result['case_number'] ?? null),
                'parties'               => $nlpResult['parties'] ?? ($result['parties'] ?? []),
                'date_of_document'      => $nlpResult['date_of_document'] ?? ($result['date_of_document'] ?? null),
                'court'                 => $nlpResult['court'] ?? ($result['court'] ?? null),
                'judge'                 => $nlpResult['judge'] ?? ($result['judge'] ?? null),
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
                        'maxOutputTokens' => 2048,
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
            $result['professional_summary'] = $this->limitWords((string) ($result['professional_summary'] ?? ''), 700);
            $result['executive_summary'] = $this->limitWords((string) (($result['executive_summary'] ?? '') ?: $result['professional_summary']), 700);
            $panels['mode_sections'] = [
                'Document Type' => $result['document_type'] ?? null,
                'Citation / Court Details' => trim(implode(' | ', array_filter([
                    $result['case_number'] ?? null,
                    $result['court'] ?? null,
                    $result['judge'] ?? null,
                    $result['date_of_document'] ?? null,
                ]))) ?: null,
                'Facts' => $result['professional_summary'] ?? $result['executive_summary'] ?? null,
                'Legal Issues' => $result['key_issues'] ?? ($panels['key_legal_issues'] ?? []),
                'Holding / Decision' => $result['outcome'] ?? null,
                'Ratio Decidendi' => $result['legal_principles'] ?? null,
                'Orders / Remedies' => $result['key_obligations'] ?? [],
                'Authorities Cited' => $result['legal_references'] ?? ($panels['important_legal_references'] ?? []),
                'Cited Instruments' => $panels['cited_instruments'] ?? ($result['cited_instruments'] ?? []),
            ];
        } else {
            $result['citizen_summary'] = $this->limitWords((string) ($result['citizen_summary'] ?? ''), 200);
            $result['executive_summary'] = $this->limitWords((string) (($result['executive_summary'] ?? '') ?: $result['citizen_summary']), 200);
            $panels['mode_sections'] = [
                'What Happened' => $result['citizen_summary'] ?? $result['executive_summary'] ?? null,
                'Main Issue' => $result['key_issues'] ?? ($panels['key_legal_issues'] ?? []),
                'Decision / Outcome' => $result['outcome'] ?? null,
                'What This Means' => $result['practical_implications'] ?? null,
            ];
        }

        $result['structured_panels'] = $panels;

        return $result;
    }

    private function limitWords(string $text, int $limit): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= $limit) {
            return trim($text);
        }

        return implode(' ', array_slice($words, 0, $limit)) . '...';
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
