<?php

namespace Tests\Unit;

use App\Services\AiSummaryService;
use App\Services\LegalInsightService;
use App\Services\NlpService;
use App\Services\PythonNlpBridgeService;
use PHPUnit\Framework\TestCase;

class AiSummaryServiceTest extends TestCase
{
    public function test_it_parses_fenced_json_payloads(): void
    {
        $service = $this->service();
        $method = new \ReflectionMethod($service, 'parseJson');
        $method->setAccessible(true);

        $payload = <<<'JSON'
```json
{
  "document_type": "Court Judgment",
  "case_number": "",
  "parties": ["Delta Mining"],
  "date_of_document": "",
  "court": "High Court of Zimbabwe",
  "judge": "Moyo",
  "executive_summary": "Short summary.",
  "professional_summary": "Short summary.",
  "citizen_summary": "Plain summary.",
  "key_findings": "",
  "key_obligations": [],
  "legal_principles": "",
  "outcome": "",
  "practical_implications": "",
  "key_issues": [],
  "legal_references": []
}
```
JSON;

        $parsed = $method->invoke($service, $payload);

        $this->assertIsArray($parsed);
        $this->assertSame('Court Judgment', $parsed['document_type']);
        $this->assertSame(['Delta Mining'], $parsed['parties']);
    }

    public function test_it_normalises_nullable_fields_and_lists(): void
    {
        $service = $this->service();
        $method = new \ReflectionMethod($service, 'normaliseAiPayload');
        $method->setAccessible(true);

        $normalised = $method->invoke($service, [
            'document_type' => 'Court Judgment',
            'case_number' => 'Unknown',
            'parties' => [' Delta Mining ', '', 'Delta Mining'],
            'date_of_document' => ' ',
            'court' => 'High Court of Zimbabwe',
            'judge' => 'Moyo',
            'executive_summary' => ' Summary here. ',
            'professional_summary' => ' Professional summary. ',
            'citizen_summary' => ' Citizen summary. ',
            'key_findings' => 'not specified',
            'key_obligations' => [' file record ', '', 'file record'],
            'legal_principles' => 'N/A',
            'outcome' => 'null',
            'practical_implications' => 'None',
            'key_issues' => [' licensing ', 'licensing'],
            'legal_references' => ['Section 97', 'Section 97'],
        ]);

        $this->assertNull($normalised['case_number']);
        $this->assertNull($normalised['date_of_document']);
        $this->assertNull($normalised['key_findings']);
        $this->assertNull($normalised['legal_principles']);
        $this->assertNull($normalised['outcome']);
        $this->assertNull($normalised['practical_implications']);
        $this->assertSame(['Delta Mining'], $normalised['parties']);
        $this->assertSame(['file record'], $normalised['key_obligations']);
        $this->assertSame(['licensing'], $normalised['key_issues']);
        $this->assertSame(['Section 97'], $normalised['legal_references']);
        $this->assertSame('Summary here.', $normalised['executive_summary']);
    }

    public function test_it_exposes_a_complete_gemini_schema(): void
    {
        $service = $this->service();
        $method = new \ReflectionMethod($service, 'geminiResponseSchema');
        $method->setAccessible(true);

        $schema = $method->invoke($service);

        $this->assertSame('OBJECT', $schema['type']);
        $this->assertContains('document_type', $schema['required']);
        $this->assertContains('executive_summary', $schema['required']);
        $this->assertContains('key_issues', $schema['required']);
        $this->assertSame('ARRAY', $schema['properties']['parties']['type']);
    }

    private function service(): AiSummaryService
    {
        return new AiSummaryService(
            new NlpService(),
            new LegalInsightService(new PythonNlpBridgeService())
        );
    }
}
