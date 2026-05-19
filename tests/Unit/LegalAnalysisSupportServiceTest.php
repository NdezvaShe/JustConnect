<?php

namespace Tests\Unit;

use App\Services\LegalAnalysisSupportService;
use PHPUnit\Framework\TestCase;

class LegalAnalysisSupportServiceTest extends TestCase
{
    public function test_it_returns_quotes_page_references_and_risk_actions(): void
    {
        $text = file_get_contents(__DIR__ . '/../Fixtures/employment_contract_excerpt.txt');
        $service = new LegalAnalysisSupportService();

        $summary = [
            'document_type' => 'Contract Agreement',
            'case_number' => null,
            'parties' => ['JustConnect Legal Services', 'Tariro Dube'],
            'date_of_document' => '14 February 2026',
            'court' => null,
            'judge' => null,
            'executive_summary' => 'Employment agreement covering pay, confidentiality, termination, and grievance timing.',
            'key_findings' => 'The contract sets a start date, monthly salary, notice-based termination, and confidentiality duties.',
            'key_obligations' => [
                'The employer must pay a gross monthly salary of USD 1,200 on or before the last business day of each month.',
                'Either party may terminate this agreement by giving 30 days written notice.',
            ],
            'outcome' => 'If the employee commits a material breach, the employer may suspend or terminate employment in accordance with the Labour Act.',
            'practical_implications' => 'Review pay dates, notice periods, confidentiality obligations, and grievance timelines before implementation.',
            'nlp_entities' => [
                'persons' => ['Tariro Dube'],
                'organisations' => ['JustConnect Legal Services'],
                'dates' => ['14 February 2026'],
                'amounts' => ['USD 1,200'],
            ],
        ];

        $support = $service->build($text, $summary);

        $this->assertSame('high', $support['legal_risk']['urgency']);
        $this->assertNotEmpty($support['evidence']['findings']);
        $this->assertSame(1, $support['evidence']['obligations'][0]['page']);
        $this->assertStringContainsString('must pay a gross monthly salary', $support['evidence']['obligations'][0]['quote']);
        $this->assertNotEmpty($support['legal_risk']['deadlines']);
        $this->assertNotEmpty($support['legal_risk']['recommended_actions']);
    }

    public function test_it_flattens_nested_label_entities_without_array_conversion_errors(): void
    {
        $text = file_get_contents(__DIR__ . '/../Fixtures/employment_contract_excerpt.txt');
        $service = new LegalAnalysisSupportService();

        $summary = [
            'document_type' => 'Contract Agreement',
            'case_number' => null,
            'parties' => ['JustConnect Legal Services', 'Tariro Dube'],
            'date_of_document' => '14 February 2026',
            'court' => null,
            'judge' => null,
            'executive_summary' => 'Employment agreement covering pay, confidentiality, termination, and grievance timing.',
            'key_findings' => 'The contract sets a start date, monthly salary, notice-based termination, and confidentiality duties.',
            'key_obligations' => [],
            'outcome' => null,
            'practical_implications' => null,
            'nlp_entities' => [
                'persons' => ['Tariro Dube'],
                'labels' => [
                    'PERSON' => ['Tariro Dube'],
                    'ORGANISATION' => ['JustConnect Legal Services'],
                    'EMPTY' => [' ', ''],
                ],
            ],
        ];

        $support = $service->build($text, $summary);

        $this->assertArrayHasKey('labels', $support['evidence']['entities']);
        $this->assertSame('Tariro Dube', $support['evidence']['entities']['labels'][0]['value']);
        $this->assertStringContainsString('Tariro Dube', $support['evidence']['entities']['labels'][0]['quote']);
        $this->assertSame('JustConnect Legal Services', $support['evidence']['entities']['labels'][1]['value']);
    }
}
