<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NlpAdaptiveLearningService
{
    private const MODEL_PATH = 'nlp_learning/adaptive_model.json';

    private ?array $model = null;

    public function learn(array $example): void
    {
        $inputText = trim((string) ($example['input_text'] ?? ''));
        $targetSummary = trim((string) ($example['target_summary'] ?? ''));
        if ($inputText === '' || $targetSummary === '') {
            return;
        }

        try {
            $model = $this->model();
            $model['examples_seen'] = (int) ($model['examples_seen'] ?? 0) + 1;
            $model['updated_at'] = now()->toIso8601String();

            foreach ($this->learnedTerms($inputText, $targetSummary) as $term => $weight) {
                $model['term_weights'][$term] = round(((float) ($model['term_weights'][$term] ?? 0)) + $weight, 4);
            }

            foreach ((array) ($example['labels']['legal_categories'] ?? []) as $category) {
                $category = trim((string) $category);
                if ($category === '') {
                    continue;
                }

                foreach ($this->learnedTerms($inputText, $category) as $term => $weight) {
                    $model['category_terms'][$category][$term] = round(((float) ($model['category_terms'][$category][$term] ?? 0)) + $weight, 4);
                }
            }

            $model['term_weights'] = $this->topWeighted($model['term_weights'] ?? [], 600);
            foreach (($model['category_terms'] ?? []) as $category => $terms) {
                $model['category_terms'][$category] = $this->topWeighted($terms, 80);
            }

            Storage::disk('local')->makeDirectory('nlp_learning');
            Storage::disk('local')->put(self::MODEL_PATH, json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->model = $model;
        } catch (\Throwable $e) {
            Log::warning('JustConnect: adaptive NLP learning failed.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function keywordBoosts(string $text): array
    {
        $haystack = mb_strtolower($text);
        $boosts = [];

        foreach (($this->model()['term_weights'] ?? []) as $term => $weight) {
            if (str_contains($haystack, (string) $term)) {
                $boosts[(string) $term] = min(4.0, max(0.25, ((float) $weight) / max(1, (int) ($this->model()['examples_seen'] ?? 1))));
            }
        }

        return $boosts;
    }

    public function categoriesForText(string $text): array
    {
        $haystack = mb_strtolower($text);
        $matches = [];

        foreach (($this->model()['category_terms'] ?? []) as $category => $terms) {
            $score = 0.0;
            foreach ($terms as $term => $weight) {
                if (str_contains($haystack, (string) $term)) {
                    $score += (float) $weight;
                }
            }

            if ($score >= 2.0) {
                $matches[$category] = $score;
            }
        }

        arsort($matches);

        return array_slice(array_keys($matches), 0, 4);
    }

    private function model(): array
    {
        if ($this->model !== null) {
            return $this->model;
        }

        if (!Storage::disk('local')->exists(self::MODEL_PATH)) {
            return $this->model = [
                'version' => 1,
                'examples_seen' => 0,
                'updated_at' => null,
                'term_weights' => [],
                'category_terms' => [],
            ];
        }

        $decoded = json_decode((string) Storage::disk('local')->get(self::MODEL_PATH), true);

        return $this->model = is_array($decoded) ? $decoded : [];
    }

    private function learnedTerms(string ...$texts): array
    {
        $joined = mb_strtolower(implode("\n", $texts));
        preg_match_all("/\p{L}[\p{L}\p{Mn}'-]{3,}(?:\s+\p{L}[\p{L}\p{Mn}'-]{3,}){0,2}/u", $joined, $matches);

        $stop = array_flip([
            'that', 'this', 'with', 'from', 'were', 'have', 'been', 'will', 'must',
            'shall', 'court', 'document', 'legal', 'summary', 'person', 'people',
            'case', 'matter', 'judge', 'applicant', 'respondent',
        ]);

        $terms = [];
        foreach ($matches[0] ?? [] as $term) {
            $term = trim((string) preg_replace('/\s+/u', ' ', $term));
            if ($term === '' || isset($stop[$term]) || mb_strlen($term) < 4) {
                continue;
            }

            $terms[$term] = ($terms[$term] ?? 0) + (str_contains($term, ' ') ? 1.4 : 1.0);
        }

        arsort($terms);

        return array_slice($terms, 0, 40, true);
    }

    private function topWeighted(array $weights, int $limit): array
    {
        arsort($weights);

        return array_slice($weights, 0, $limit, true);
    }
}
