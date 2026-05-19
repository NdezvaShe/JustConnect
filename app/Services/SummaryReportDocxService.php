<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Summary;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class SummaryReportDocxService
{
    public function generate(Summary $summary, ?Document $document = null): string
    {
        $identifier = $summary->id ?: ('draft_' . now()->format('YmdHis'));
        $path = storage_path('app/reports/justconnect_summary_' . $identifier . '.docx');
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelsXml());
        $zip->addFromString('word/document.xml', $this->documentXml($summary, $document));
        $zip->close();

        $storagePath = 'reports/' . basename($path);
        Storage::disk('local')->put($storagePath, file_get_contents($path) ?: '');

        return $storagePath;
    }

    private function documentXml(Summary $summary, ?Document $document): string
    {
        $panels = json_decode($summary->structured_panels ?? '[]', true) ?: [];
        $cards = json_decode($summary->result_cards ?? '[]', true) ?: [];
        $passages = json_decode($summary->supporting_passages ?? '[]', true) ?: [];
        $isLegalProfessionalSummary = ($summary->summary_type ?: 'general_user') === 'legal_professional';
        $documentName = $document?->original_name ?: ('Summary #' . $summary->id);

        $blocks = [
            $this->paragraph('JustConnect Legal Insight Report', 32, true),
            $this->paragraph('Document: ' . $documentName, 22, false),
            $this->paragraph('Generated: ' . now()->format('d M Y H:i'), 20, false),
            $this->paragraph('Case Information', 26, true),
        ];

        foreach ([
            'Court' => $summary->court ?: 'Not clearly stated',
            'Judge' => $summary->judge ?: 'Not clearly stated',
            'Case Number' => $summary->case_number ?: 'Not clearly stated',
            'Date' => $summary->date_of_document ?: 'Not clearly stated',
        ] as $label => $value) {
            $blocks[] = $this->paragraph($label . ': ' . $value, 20, false);
        }

        $blocks[] = $this->paragraph('Professional Legal Summary', 26, true);
        $blocks[] = $this->paragraph($summary->professional_summary ?: $summary->executive_summary ?: 'No professional summary available.', 20, false);
        $blocks[] = $this->paragraph('Citizen-Friendly Summary', 26, true);
        $blocks[] = $this->paragraph($summary->citizen_summary ?: 'No citizen summary available.', 20, false);

        foreach ($cards as $card) {
            $blocks[] = $this->paragraph((string) ($card['title'] ?? 'Insight Card'), 24, true);
            foreach ((array) ($card['items'] ?? []) as $item) {
                $blocks[] = $this->paragraph('• ' . $item, 20, false);
            }
        }

        if ($isLegalProfessionalSummary) {
            foreach ([
            'key_legal_issues' => 'Key Legal Issues',
            'cited_instruments' => 'Cited Instruments',
            'important_legal_references' => 'Important Legal References',
            'people_and_organisations' => 'People and Organisations',
            'constitutional_rights_affected' => 'Constitutional Rights Affected',
            ] as $key => $title) {
            $items = (array) ($panels[$key] ?? []);
            if ($items === []) {
                continue;
            }

            $blocks[] = $this->paragraph($title, 24, true);
            foreach ($items as $item) {
                $blocks[] = $this->paragraph('• ' . $item, 20, false);
            }
            }
        }

        if ($isLegalProfessionalSummary && $passages !== []) {
            $blocks[] = $this->paragraph('Supporting Passages', 24, true);
            foreach (array_slice($passages, 0, 4) as $passage) {
                $blocks[] = $this->paragraph(
                    '[Page ' . ($passage['page'] ?? '?') . '] ' . ($passage['text'] ?? ''),
                    19,
                    false
                );
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"'
            . ' xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"'
            . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"'
            . ' xmlns:v="urn:schemas-microsoft-com:vml"'
            . ' xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing"'
            . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            . ' xmlns:w10="urn:schemas-microsoft-com:office:word"'
            . ' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            . ' xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"'
            . ' xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup"'
            . ' xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk"'
            . ' xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml"'
            . ' xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"'
            . ' mc:Ignorable="w14 wp14">'
            . '<w:body>' . implode('', $blocks) . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="900" w:right="900" w:bottom="900" w:left="900" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr></w:body></w:document>';
    }

    private function paragraph(string $text, int $size, bool $bold): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $runProps = '<w:rPr>' . ($bold ? '<w:b/>' : '') . '<w:sz w:val="' . $size . '"/></w:rPr>';

        return '<w:p><w:r>' . $runProps . '<w:t xml:space="preserve">' . $escaped . '</w:t></w:r></w:p>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private function documentRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
    }
}
