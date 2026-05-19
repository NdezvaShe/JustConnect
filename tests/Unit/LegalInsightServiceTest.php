<?php

namespace Tests\Unit;

use App\Services\LegalInsightService;
use App\Services\PythonNlpBridgeService;
use PHPUnit\Framework\TestCase;

class LegalInsightServiceTest extends TestCase
{
    public function test_it_builds_cards_dual_summaries_and_structured_panels(): void
    {
        $service = new LegalInsightService(new PythonNlpBridgeService());

        $text = "——— PAGE 1 ———\n\nIN THE HIGH COURT OF ZIMBABWE\nHARARE\nHH 223-24\n\n"
            . "The appeal is dismissed with costs. The applicant shall pay the respondent's costs.\n\n"
            . "Section 56 of the Constitution and Chapter 28:01 of the Labour Act were considered in relation to unfair dismissal.\n\n"
            . "Justice Chitapi held that the Judicial Service Commission acted lawfully.";

        $base = [
            'document_type' => 'Court Judgment',
            'case_number' => 'HH 223-24',
            'parties' => ['John Moyo', 'Judicial Service Commission'],
            'date_of_document' => '12 March 2025',
            'court' => 'High Court of Zimbabwe',
            'judge' => 'Justice Chitapi',
            'executive_summary' => 'The court dismissed the appeal after considering whether the dismissal process complied with labour and constitutional protections.',
            'outcome' => 'The appeal is dismissed with costs.',
            'key_findings' => 'The court found that the employer followed the required process.',
            'key_obligations' => ['The applicant shall pay costs.'],
            'legal_principles' => 'Section 56 of the Constitution and Chapter 28:01 of the Labour Act govern fairness and equality.',
            'clauses' => [
                [
                    'clause_type' => 'COURT_ORDER',
                    'heading' => 'Court Order',
                    'content' => 'The appeal is dismissed with costs.',
                ],
            ],
            'nlp_entities' => [
                'persons' => ['Justice Chitapi', 'John Moyo'],
                'organisations' => ['Judicial Service Commission'],
                'dates' => ['12 March 2025'],
                'courts' => ['High Court of Zimbabwe'],
            ],
            'nlp_keywords' => ['unfair dismissal', 'section 56 constitution', 'labour act chapter 28:01'],
            'nlp_legal_categories' => ['Labour Law', 'Constitutional Law'],
        ];

        $result = $service->enrich($text, $base);

        $this->assertNotEmpty($result['citizen_summary']);
        $this->assertNotEmpty($result['professional_summary']);
        $this->assertNotEmpty($result['result_cards']);
        $this->assertSame('Case Result', $result['result_cards'][0]['title']);
        $this->assertContains('Appeal dismissed', $result['result_cards'][0]['items']);
        $this->assertContains('Labour dispute', $result['structured_panels']['key_legal_issues']);
        $this->assertStringContainsString(
            'Section 56',
            implode(' ', $result['structured_panels']['important_legal_references'])
        );
        $this->assertContains('High Court of Zimbabwe', $result['nlp_entities']['labels']['COURT_NAME']);
        $this->assertContains('HH 223-24', $result['nlp_entities']['labels']['CASE_NUMBER']);
        $this->assertNotEmpty($result['supporting_passages']);
        $this->assertNotEmpty($result['semantic_profile']['vector']);
        $this->assertNotEmpty($result['structured_extraction']['clauses'] ?? []);
        $this->assertSame('Court Judgment', $result['structured_extraction']['document_type']);
    }

    public function test_it_builds_a_citizen_summary_from_outcome_and_practical_effect_even_when_executive_summary_exists(): void
    {
        $service = new LegalInsightService(new PythonNlpBridgeService());

        $text = "IN THE HIGH COURT OF ZIMBABWE\nHARARE\nHH 223-24\n\n"
            . "The appeal is dismissed with costs. The applicant shall pay the respondent's costs.\n\n"
            . "Section 56 of the Constitution and Chapter 28:01 of the Labour Act were considered in relation to unfair dismissal.";

        $base = [
            'document_type' => 'Court Judgment',
            'case_number' => 'HH 223-24',
            'parties' => ['John Moyo', 'Judicial Service Commission'],
            'date_of_document' => '12 March 2025',
            'court' => 'High Court of Zimbabwe',
            'judge' => 'Justice Chitapi',
            'executive_summary' => 'The court considered labour and constitutional protections arising from the dismissal process.',
            'outcome' => 'The appeal is dismissed with costs.',
            'practical_implications' => 'The applicant must pay costs and should verify any further appeal deadlines immediately.',
            'key_findings' => 'The court found that the employer followed the required process.',
            'key_obligations' => ['The applicant shall pay costs.'],
            'legal_principles' => 'Section 56 of the Constitution and Chapter 28:01 of the Labour Act govern fairness and equality.',
            'nlp_entities' => [
                'persons' => ['Justice Chitapi', 'John Moyo'],
                'organisations' => ['Judicial Service Commission'],
                'dates' => ['12 March 2025'],
                'courts' => ['High Court of Zimbabwe'],
            ],
            'nlp_keywords' => ['unfair dismissal', 'section 56 constitution', 'labour act chapter 28:01'],
            'nlp_legal_categories' => ['Labour Law', 'Constitutional Law'],
        ];

        $result = $service->enrich($text, $base);

        $this->assertStringContainsString('In everyday terms, the result is that the appeal is dismissed with costs.', $result['citizen_summary']);
        $this->assertStringContainsString('What this means in practice is that the person bringing the case must pay costs', $result['citizen_summary']);
    }

    public function test_it_regenerates_citizen_summary_when_the_ai_version_is_too_close_to_the_legal_summary(): void
    {
        $service = new LegalInsightService(new PythonNlpBridgeService());

        $text = "IN THE HIGH COURT OF ZIMBABWE\nHARARE\nHH 223-24\n\n"
            . "The appeal is dismissed with costs. The applicant shall pay the respondent's costs.\n\n"
            . "Section 56 of the Constitution and Chapter 28:01 of the Labour Act were considered in relation to unfair dismissal.";

        $base = [
            'document_type' => 'Court Judgment',
            'case_number' => 'HH 223-24',
            'parties' => ['John Moyo', 'Judicial Service Commission'],
            'date_of_document' => '12 March 2025',
            'court' => 'High Court of Zimbabwe',
            'judge' => 'Justice Chitapi',
            'executive_summary' => 'The court dismissed the appeal after considering labour and constitutional protections.',
            'professional_summary' => 'The court dismissed the appeal after considering labour and constitutional protections.',
            'citizen_summary' => 'The court dismissed the appeal after considering labour and constitutional protections.',
            'outcome' => 'The appeal is dismissed with costs.',
            'practical_implications' => 'The applicant must pay costs and should verify any further appeal deadlines immediately.',
            'key_findings' => 'The court found that the employer followed the required process.',
            'key_obligations' => ['The applicant shall pay costs.'],
            'legal_principles' => 'Section 56 of the Constitution and Chapter 28:01 of the Labour Act govern fairness and equality.',
            'nlp_entities' => [
                'persons' => ['Justice Chitapi', 'John Moyo'],
                'organisations' => ['Judicial Service Commission'],
                'dates' => ['12 March 2025'],
                'courts' => ['High Court of Zimbabwe'],
            ],
            'nlp_keywords' => ['unfair dismissal', 'section 56 constitution', 'labour act chapter 28:01'],
            'nlp_legal_categories' => ['Labour Law', 'Constitutional Law'],
        ];

        $result = $service->enrich($text, $base);

        $this->assertNotSame($base['citizen_summary'], $result['citizen_summary']);
        $this->assertStringContainsString('In everyday terms, the result is that the appeal is dismissed with costs.', $result['citizen_summary']);
        $this->assertStringContainsString('person bringing the case', $result['citizen_summary']);
    }
}
