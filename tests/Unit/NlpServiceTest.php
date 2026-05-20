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

    public function test_it_keeps_extracted_clause_snippets_concise_and_removes_page_noise(): void
    {
        $service = new NlpService();

        $longTermination = str_repeat(
            'The tenant may terminate this agreement on written notice if the landlord materially breaches the lease obligations. ',
            8
        );

        $text = "LEASE AGREEMENT\n"
            . "--- PAGE 1 ---\n\n"
            . "1. Termination\n{$longTermination}\n\n"
            . "2. Payment Terms\nPage 2 of 4\nThe tenant shall pay rent by the first day of each month.";

        $result = $service->analyse($text, 'lease.pdf');
        $termination = null;
        foreach ($result['clauses'] as $clause) {
            if (($clause['heading'] ?? null) === '1. Termination') {
                $termination = $clause;
                break;
            }
        }

        $this->assertNotNull($termination);
        $this->assertStringNotContainsString('PAGE', strtoupper($termination['content']));
        $this->assertLessThanOrEqual(72, count(preg_split('/\s+/u', trim($termination['content']), -1, PREG_SPLIT_NO_EMPTY)));
        $this->assertLessThanOrEqual(8, count($result['clauses']));
    }

    public function test_it_does_not_reduce_court_outcome_to_costs_fragment(): void
    {
        $service = new NlpService();

        $text = "IN THE SUPREME COURT OF ZIMBABWE\n"
            . "The appellant challenged the decision of the court below.\n"
            . "After considering the agreement and the evidence, the court found that the appeal lacked merit.\n"
            . "Accordingly, the appeal is dismissed with costs.";

        $result = $service->analyse($text, 'appeal.txt');

        $this->assertStringContainsString('appeal is dismissed with costs', strtolower($result['outcome']));
        $this->assertNotSame('with costs.', strtolower($result['outcome']));
    }

    public function test_it_does_not_treat_high_court_rules_as_the_judge(): void
    {
        $service = new NlpService();

        $text = "IN THE HIGH COURT OF ZIMBABWE\n"
            . "High Court of Zimbabwe Rules, 2021\n"
            . "ALLIED TRADING CC\n"
            . "versus\n"
            . "STANLEY MOYO\n"
            . "Subject to this order, a decree of civil imprisonment is hereby granted against the defendant.";

        $result = $service->analyse($text, 'civil_imprisonment.txt');

        $this->assertNotSame('Rules', $result['judge']);
        $this->assertStringNotContainsString('handled by Rules', $result['executive_summary']);
    }

    public function test_court_judgment_practical_implications_do_not_default_to_environmental_advice(): void
    {
        $service = new NlpService();

        $text = "IN THE HIGH COURT OF ZIMBABWE\n"
            . "The plaintiff sought payment of a judgment debt and civil imprisonment of the defendant.\n"
            . "The defendant was ordered to pay the judgment debt within the stated period.";

        $result = $service->analyse($text, 'judgment.txt');

        $this->assertStringContainsString('court order', strtolower($result['practical_implications']));
        $this->assertStringNotContainsString('environmental approvals', strtolower($result['practical_implications']));
    }

    public function test_it_distinguishes_supported_document_subtypes(): void
    {
        $service = new NlpService();

        $cases = [
            'lease' => [
                "LEASE AGREEMENT\nThe landlord lets the premises to the tenant for a monthly rental. The tenant shall pay a security deposit and comply with the permitted use clause.",
                'office_lease.pdf',
                'Lease Agreement',
            ],
            'employment' => [
                "EMPLOYMENT CONTRACT\nThe employer shall pay the employee a gross monthly salary. The employee is entitled to annual leave and must follow disciplinary and grievance procedures.",
                'employment_contract.pdf',
                'Employment Contract',
            ],
            'sale' => [
                "AGREEMENT OF SALE\nThe vendor sells the property to the purchaser for the purchase price. Transfer of ownership shall occur after payment and delivery.",
                'sale_agreement.pdf',
                'Sale Agreement',
            ],
            'loan' => [
                "LOAN AGREEMENT\nThe lender advances the principal amount to the borrower. The borrower shall repay the loan in monthly instalments with interest and collateral as security.",
                'loan_facility.pdf',
                'Loan Agreement',
            ],
            'shareholders' => [
                "SHAREHOLDERS AGREEMENT\nThe shareholders agree on voting rights, reserved matters, transfer of shares, dividends, and board of directors appointment rights.",
                'shareholders_agreement.pdf',
                'Shareholder Agreement',
            ],
            'power of attorney' => [
                "SPECIAL POWER OF ATTORNEY\nI appoint Tendai Moyo as my attorney to act on my behalf and sign documents for the transfer of property.",
                'special_power_of_attorney.pdf',
                'Power of Attorney',
            ],
            'will' => [
                "LAST WILL AND TESTAMENT\nI appoint an executor for my estate and bequeath the residue of my estate to the named beneficiaries.",
                'last_will.pdf',
                'Will and Testament',
            ],
            'statutory instrument' => [
                "Statutory Instrument 12 of 2026. It is hereby notified that the Minister has made these Regulations in terms of section 97 of the enabling Act.",
                'si_12_2026.pdf',
                'Statutory Instrument',
            ],
        ];

        foreach ($cases as $label => [$text, $filename, $expectedType]) {
            $result = $service->analyse($text, $filename);
            $this->assertSame($expectedType, $result['document_type'], $label);
            $this->assertSame($expectedType, $result['structured_extraction']['document_type'], $label);
        }
    }

    public function test_it_uses_a_generic_fallback_for_ambiguous_text(): void
    {
        $service = new NlpService();

        $result = $service->analyse(
            'This note records a general conversation about next steps and does not contain legal document headings or operative clauses.',
            'notes.txt'
        );

        $this->assertSame('Legal Document', $result['document_type']);
    }

    public function test_it_prefers_act_titles_over_judgment_like_body_terms(): void
    {
        $service = new NlpService();

        $text = "ADMINISTRATIVE JUSTICE ACT [CHAPTER 10:28]\n"
            . "ARRANGEMENT OF SECTIONS\n\n"
            . "8 Appeals to the High Court\n"
            . "An applicant or respondent may appeal to the High Court against the decision of an administrative authority. "
            . "The court may make such order as it thinks fit. Any person who fails to comply shall be guilty of an offence.";

        $result = $service->analyse($text, 'uploaded-document.pdf');

        $this->assertSame('Act', $result['document_type']);
    }

    public function test_act_summary_rewrites_numbered_subsections_into_plain_sentences(): void
    {
        $service = new NlpService();

        $text = "AUDIT OFFICE ACT [Chapter 22:18]\n"
            . "This version of the Act was revised and consolidated by the Law Development Commission.\n"
            . "(6) Where the Comptroller and Auditor-General exercises duties in relation to a statutory body, subsections (1), (2) and (3) must apply,with necessary changes, in relation to the money and property of that statutory body.\n"
            . "(2) After consultation with the appropriate Minister, the Comptroller and Auditor-General may appoint registered public accountants to audit the accounts of any designated corporate body.";

        $result = $service->analyse($text, 'audit_office_act.pdf');

        $this->assertSame('Act', $result['document_type']);
        $this->assertStringContainsString('This Act', $result['executive_summary']);
        $this->assertStringNotContainsString('(6)', $result['executive_summary']);
        $this->assertStringNotContainsString('(1)', $result['executive_summary']);
        $this->assertStringNotContainsString('[Chapter', $result['executive_summary']);
        $this->assertStringNotContainsString('apply,with', $result['executive_summary']);
    }

    public function test_act_analysis_uses_opening_words_as_keywords_and_avoids_event_narration(): void
    {
        $service = new NlpService();

        $text = "ENVIRONMENTAL MANAGEMENT ACT [Chapter 20:27]\n"
            . "ARRANGEMENT OF SECTIONS\n\n"
            . "4 Functions of Agency\n"
            . "The Agency shall regulate environmental impact assessments and monitor projects likely to affect natural resources.\n"
            . "Any person who conducts a prescribed project without approval shall be guilty of an offence and liable to a penalty.";

        $result = $service->analyse($text, 'environmental_management_act.pdf');

        $this->assertSame('Act', $result['document_type']);
        $this->assertContains('environmental management', $result['nlp_keywords']);
        $this->assertContains('management act', $result['nlp_keywords']);
        $this->assertStringContainsString('This Act', $result['executive_summary']);
        $this->assertStringNotContainsString('what happened', mb_strtolower($result['executive_summary']));
        $this->assertStringNotContainsString('dispute', mb_strtolower($result['executive_summary']));
    }

    public function test_it_extracts_operational_clauses_from_acts_without_headings(): void
    {
        $service = new NlpService();

        $text = "CUSTOMARY MARRIAGES ACT Chapter 5:07\n"
            . "Every extract from a marriage register kept under this Act which is certified as a true copy by a customary marriage officer who has custody of the register shall be received as evidence.\n"
            . "A customary marriage officer may solemnize a marriage if the guardian of the woman has consented to the solemnization of the marriage.\n"
            . "Any person who knowingly makes a false entry in a marriage register shall be guilty of an offence and liable to a penalty.";

        $result = $service->analyse($text, 'customary_marriages_act.pdf');
        $types = array_column($result['clauses'], 'clause_type');
        $headings = array_column($result['clauses'], 'heading');

        $this->assertSame('Act', $result['document_type']);
        $this->assertContains('EVIDENCE_CERTIFICATE', $types);
        $this->assertContains('CONSENT', $types);
        $this->assertContains('PENALTY', $types);
        $this->assertContains('Certified Extracts', $headings);
    }

    public function test_it_uses_the_uploaded_filename_as_a_title_signal_when_text_is_procedural(): void
    {
        $service = new NlpService();

        $text = "Sections\n"
            . "An applicant may apply to the court for relief. The respondent may oppose the application. "
            . "The court may issue an order, and any person who contravenes this provision shall be liable to a fine.";

        $result = $service->analyse($text, 'Administrative Justice Act.pdf');

        $this->assertSame('Act', $result['document_type']);
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
