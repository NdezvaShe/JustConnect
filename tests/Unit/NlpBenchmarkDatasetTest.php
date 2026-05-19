<?php

namespace Tests\Unit;

use App\Services\NlpService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NlpBenchmarkDatasetTest extends TestCase
{
    private NlpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NlpService();
    }

    #[DataProvider('benchmarkDocuments')]
    public function test_benchmark_documents_cover_classification_entities_obligations_and_outcomes(
        string $fixture,
        string $filename,
        string $expectedType,
        array $expectedEntitySnippets,
        array $expectedObligationTerms,
        string $expectedOutcomeFragment
    ): void {
        $text = file_get_contents(__DIR__ . '/../Fixtures/' . $fixture);
        $result = $this->service->analyse($text, $filename);

        $this->assertSame($expectedType, $result['document_type']);

        $flattenedEntities = json_encode($result['nlp_entities']);
        foreach ($expectedEntitySnippets as $snippet) {
            $this->assertStringContainsString($snippet, $flattenedEntities);
        }

        $obligations = implode(' ', array_map(
            static fn ($item) => is_array($item) ? ($item['text'] ?? '') : (string) $item,
            $result['key_obligations']
        ));
        foreach ($expectedObligationTerms as $term) {
            $this->assertStringContainsString($term, $obligations);
        }

        $this->assertStringContainsString($expectedOutcomeFragment, $result['outcome']);
    }

    public static function benchmarkDocuments(): array
    {
        return [
            'environmental judgment' => [
                'environmental_judgment_excerpt.txt',
                'HH 12-24 judgment.pdf',
                'Court Judgment',
                ['Environmental Management Agency', 'High Court of Zimbabwe'],
                ['must cease all mining activity'],
                'application is dismissed with costs',
            ],
            'employment contract' => [
                'employment_contract_excerpt.txt',
                'employment_contract.txt',
                'Contract Agreement',
                ['JustConnect Legal Services', '14 February 2026', 'USD 1,200'],
                ['must pay a gross monthly salary', 'Either party may terminate this agreement'],
                'Outcome not expressly stated',
            ],
            'constitutional bill' => [
                'constitutional_bill_excerpt.txt',
                'Constitution Amendment Bill 2026.pdf',
                'Bill',
                ['Zimbabwe Electoral Delimitation Commission'],
                ['President shall appoint the Zimbabwe Electoral Delimitation Commission'],
                'Legislative proposal',
            ],
        ];
    }
}
