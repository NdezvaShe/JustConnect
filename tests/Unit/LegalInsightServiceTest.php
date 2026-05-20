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

    public function test_it_keeps_act_summaries_in_a_legislative_frame(): void
    {
        $service = new LegalInsightService(new PythonNlpBridgeService());

        $text = "CUSTOMARY MARRIAGES ACT Chapter 5:07\n\n"
            . "This Act may be cited as the Customary Marriages Act Chapter 5:07.\n\n"
            . "Every extract from a marriage register kept under this Act which purports to be certified as a true copy by a customary marriage officer who has custody of the register shall be received as evidence.\n\n"
            . "A customary marriage officer may solemnize a marriage if the guardian of the woman has consented and the parties have agreed to the form of the marriage.\n\n"
            . "The Minister may appoint officials or chiefs to be customary marriage officers and may make regulations for registration.";

        $base = [
            'document_type' => 'Act',
            'case_number' => null,
            'parties' => [],
            'date_of_document' => null,
            'court' => null,
            'judge' => null,
            'executive_summary' => 'The main facts are that every extract from a marriage register must be certified. At its core, the document is about Breach of contract. The court reasoned that a customary marriage officer may solemnize a marriage.',
            'professional_summary' => '',
            'citizen_summary' => 'The main facts are that every extract from a marriage register must be certified. At its core, the document is about Breach of contract. The court reasoned that a customary marriage officer may solemnize a marriage.',
            'outcome' => 'Legislative instrument setting out binding legal rules.',
            'practical_implications' => 'Compliance with this legislation is mandatory.',
            'key_findings' => 'The Act governs customary marriage officers, marriage registers, certification of extracts, consent, and registration.',
            'key_obligations' => ['Customary marriage officers must keep and certify marriage registers.', 'Officials must follow consent and registration requirements.'],
            'legal_principles' => '',
            'legal_references' => ['Customary Marriages Act Chapter 5:07'],
            'clauses' => [],
            'nlp_entities' => ['persons' => [], 'organisations' => [], 'dates' => [], 'courts' => []],
            'nlp_keywords' => ['customary marriages act', 'marriage register', 'customary marriage officer'],
            'nlp_legal_categories' => ['Contract Law'],
            'summary_type' => 'general_user',
        ];

        $result = $service->enrich($text, $base);
        $summary = mb_strtolower($result['citizen_summary']);

        $this->assertStringContainsString('customary marriage', $summary);
        $this->assertStringContainsString('register', $summary);
        $this->assertStringNotContainsString('main facts', $summary);
        $this->assertStringNotContainsString('court reasoned', $summary);
        $this->assertStringNotContainsString('breach of contract', $summary);
        $this->assertNotContains('Breach of contract', $result['structured_panels']['key_legal_issues']);
    }

    public function test_it_rebuilds_weak_professional_summaries_with_legal_anchors(): void
    {
        $service = new LegalInsightService(new PythonNlpBridgeService());

        $text = "IN THE HIGH COURT OF ZIMBABWE\nHH 18-26\n\n"
            . "The applicant sought an interdict against the respondent. The court held that the requirements for an interdict were not satisfied because there was no clear right and no irreparable harm. The application is dismissed with costs.\n\n"
            . "Section 85 of the Constitution and the High Court Act were considered.";

        $base = [
            'document_type' => 'Court Judgment',
            'case_number' => 'HH 18-26',
            'parties' => ['Tariro Moyo', 'City of Harare'],
            'date_of_document' => null,
            'court' => 'High Court of Zimbabwe',
            'judge' => null,
            'executive_summary' => 'The application for an interdict was dismissed because the requirements for interim relief were not met.',
            'professional_summary' => 'This document explains what happened and what this means for the people involved.',
            'citizen_summary' => '',
            'outcome' => 'The application is dismissed with costs.',
            'practical_implications' => 'The applicant remains without interim relief and must pay costs.',
            'key_findings' => 'The court found no clear right and no irreparable harm.',
            'key_obligations' => ['The applicant must pay costs.'],
            'legal_principles' => 'An interdict requires a clear right, irreparable harm, and absence of another remedy.',
            'legal_references' => ['Section 85 of the Constitution', 'High Court Act'],
            'clauses' => [
                ['clause_type' => 'COURT_ORDER', 'heading' => 'Court Order', 'content' => 'The application is dismissed with costs.'],
            ],
            'nlp_entities' => ['persons' => [], 'organisations' => [], 'dates' => [], 'courts' => ['High Court of Zimbabwe']],
            'nlp_keywords' => ['interdict', 'clear right', 'irreparable harm'],
            'nlp_legal_categories' => ['Constitutional Law'],
        ];

        $result = $service->enrich($text, $base);
        $summary = mb_strtolower($result['professional_summary']);

        $this->assertStringContainsString('interdict', $summary);
        $this->assertStringContainsString('dismissed', $summary);
        $this->assertStringContainsString('section 85', $summary);
        $this->assertStringNotContainsString('what this means', $summary);
    }
}
