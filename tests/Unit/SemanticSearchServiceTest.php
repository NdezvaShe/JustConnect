<?php

namespace Tests\Unit;

use App\Services\LegalInsightService;
use App\Services\PythonNlpBridgeService;
use App\Services\SemanticSearchService;
use PHPUnit\Framework\TestCase;

class SemanticSearchServiceTest extends TestCase
{
    public function test_it_ranks_semantically_related_issues_ahead_of_unrelated_cases(): void
    {
        $insights = new LegalInsightService(new PythonNlpBridgeService());
        $search = new SemanticSearchService($insights);

        $employment = $insights->enrich('Unfair dismissal and labour dispute over termination and salary.', [
            'document_type' => 'Court Judgment',
            'executive_summary' => 'A labour dispute over termination of employment and unfair dismissal.',
            'outcome' => 'The appeal is dismissed.',
            'nlp_entities' => ['persons' => [], 'organisations' => [], 'dates' => [], 'courts' => []],
            'nlp_keywords' => ['unfair dismissal', 'termination of employment'],
            'nlp_legal_categories' => ['Labour Law'],
        ]);

        $property = $insights->enrich('Property ownership, title deed transfer, and land boundary dispute.', [
            'document_type' => 'Court Judgment',
            'executive_summary' => 'A property dispute over title and land ownership.',
            'outcome' => 'Application granted.',
            'nlp_entities' => ['persons' => [], 'organisations' => [], 'dates' => [], 'courts' => []],
            'nlp_keywords' => ['property dispute', 'title deed'],
            'nlp_legal_categories' => ['Property Law'],
        ]);

        $ranked = $search->rank('unfair dismissal', [$property, $employment]);

        $this->assertCount(2, $ranked);
        $this->assertSame('A labour dispute over termination of employment and unfair dismissal.', $ranked[0]['executive_summary']);
        $this->assertGreaterThan($ranked[1]['search_score'], $ranked[0]['search_score']);
    }
}
