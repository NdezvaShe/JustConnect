<?php

namespace Tests\Unit;

use App\Services\NlpService;
use PHPUnit\Framework\TestCase;

class NlpServiceTest extends TestCase
{
    public function test_it_promotes_legal_phrases_and_environmental_categories(): void
    {
        $service = new NlpService();

        $text = 'The Certificates of Registration were issued before the acquisition of an Environmental Impact Assessment Certificate (EIAC). '
            . 'Section 97 provides that projects listed in the First Schedule must not be implemented unless the Director-General has issued a certificate '
            . 'following the submission of an environmental impact assessment report.';

        $result = $service->analyse($text, 'extract.txt');

        $this->assertContains('environmental impact assessment', $result['nlp_keywords']);
        $this->assertContains('Environmental Law', $result['nlp_legal_categories']);
    }

    public function test_it_does_not_invent_an_outcome_when_the_text_has_no_order(): void
    {
        $service = new NlpService();

        $text = 'According to Mr. Mudadirwa, the 5th respondent did not commence mining operations. '
            . 'Section 97 provides that projects listed in the First Schedule must not be implemented unless the Director-General has issued a certificate '
            . 'following the submission of an environmental impact assessment report.';

        $result = $service->analyse($text, 'note.txt');

        $this->assertSame(
            'Outcome not expressly stated in the extracted text. Please review the full document for the court order or dispositive clause.',
            $result['outcome']
        );
    }

    public function test_it_detects_short_shona_passages_more_reliably(): void
    {
        $service = new NlpService();

        $text = 'Munhu uyu ndiye munhu akanaka, uye zvino vanhu vanoti izvi ndizvo zvakanaka.';

        $result = $service->analyse($text, 'language.txt');

        $this->assertSame('sn', $result['nlp_language']);
    }

    public function test_it_classifies_a_constitutional_amendment_bill_and_returns_a_concise_summary(): void
    {
        $service = new NlpService();

        $text = '9 Insertion of new section in Part 3 of Chapter 7 of Constitution. '
            . 'Chapter 7 Part 3 of the Constitution is amended by the insertion of a new section 159A before section 160 as follows. '
            . 'From time to time, as may be required for the purposes of this Constitution, the President shall appoint the Zimbabwe Electoral Delimitation Commission. '
            . '10 Amendment of section 160 of Constitution. Section 160 of the Constitution is amended in subsection (1) and subsection (2) by the deletion of Zimbabwe Electoral Commission with the substitution of Zimbabwe Electoral Delimitation Commission. '
            . 'THEREFORE, be it enacted by the President and the Parliament of Zimbabwe as follows. '
            . 'This Act may be cited as the Constitution of Zimbabwe Amendment (No. 3) Bill, 2026.';

        $result = $service->analyse($text, 'Constitution Amendment (No 3) Bill 2026.pdf');

        $this->assertSame('Bill', $result['document_type']);
        $this->assertSame([], $result['parties']);
        $this->assertNull($result['court']);
        $this->assertStringContainsString('This bill', $result['executive_summary']);
        $this->assertStringContainsString('Zimbabwe Electoral Delimitation Commission', $result['executive_summary']);
        $this->assertStringNotContainsString("\n", $result['executive_summary']);
    }

    public function test_it_generates_a_plain_english_summary_within_the_target_range_for_substantive_text(): void
    {
        $service = new NlpService();

        $text = file_get_contents(__DIR__ . '/../Fixtures/environmental_judgment_excerpt.txt');
        $result = $service->analyse($text, 'HH 12-24 judgment.pdf');

        $summary = $result['executive_summary'];
        $wordCount = count(preg_split('/\s+/u', trim($summary), -1, PREG_SPLIT_NO_EMPTY));

        $this->assertGreaterThanOrEqual(200, $wordCount);
        $this->assertLessThanOrEqual(500, $wordCount);
        $this->assertStringNotContainsString("\n", $summary);
        $this->assertStringContainsString('court', strtolower($summary));
        $this->assertStringContainsString('Environmental Management Act', $summary);
    }

    public function test_it_extracts_parties_judge_and_organisations_more_reliably_from_judgment_layouts(): void
    {
        $service = new NlpService();

        $text = "IN THE HIGH COURT OF ZIMBABWE\nHARARE\nHH 12-24\n\nBetween\nDELTA MINING (PRIVATE) LIMITED\nand\nENVIRONMENTAL MANAGEMENT AGENCY\n\nBefore: MOYO J\n\nThe applicant seeks to set aside the suspension of its mining certificate.";

        $result = $service->analyse($text, 'judgment.txt');

        $this->assertSame('High Court of Zimbabwe', $result['court']);
        $this->assertSame('Moyo', $result['judge']);
        $this->assertContains('Delta Mining (Private) Limited', $result['parties']);
        $this->assertContains('Environmental Management Agency', $result['parties']);
        $this->assertContains('Delta Mining (Private) Limited', $result['nlp_entities']['organisations']);
        $this->assertContains('Environmental Management Agency', $result['nlp_entities']['organisations']);
    }

    public function test_it_extracts_structured_clauses_and_entities_for_contract_style_documents(): void
    {
        $service = new NlpService();

        $text = "SERVICE AGREEMENT\n"
            . "1. Confidentiality\nThe parties agree to keep confidential all client information disclosed under this agreement.\n\n"
            . "2. Liability\nThe supplier's liability is limited to the contract value.\n\n"
            . "3. Termination\nEither party may terminate this agreement by giving 30 days written notice.\n\n"
            . "This Agreement is made on 12 March 2025 for USD 2,500.";

        $result = $service->analyse($text, 'service_agreement.txt');

        $this->assertSame('Contract Agreement', $result['document_type']);
        $this->assertNotEmpty($result['clauses']);
        $this->assertSame('Contract Agreement', $result['structured_extraction']['document_type']);
        $this->assertContains('12 March 2025', $result['structured_extraction']['entities']['dates']);
        $this->assertContains('USD 2,500', $result['structured_extraction']['entities']['money_amounts']);
        $this->assertContains('Confidentiality', array_column($result['clauses'], 'heading'));
    }

    public function test_it_builds_clean_contract_summaries_using_parties_and_practical_duties(): void
    {
        $service = new NlpService();

        $text = file_get_contents(__DIR__ . '/../Fixtures/employment_contract_excerpt.txt');
        $result = $service->analyse($text, 'employment_contract.txt');

        $this->assertStringContainsString('JustConnect Legal Services and Tariro Dube', $result['executive_summary']);
        $this->assertStringContainsString('Key practical duties include', $result['executive_summary']);
        $this->assertStringNotContainsString('expects the parties to the employee must', mb_strtolower($result['executive_summary']));
    }
}
