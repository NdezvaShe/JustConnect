<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Summary;
use Illuminate\Support\Facades\Storage;

class SummaryReportPdfService
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN_X = 54.0;
    private const TOP_Y = 682.0;
    private const BOTTOM_Y = 64.0;
    private const BODY_FONT_SIZE = 10.7;
    private const BODY_LEADING = self::BODY_FONT_SIZE * 1.5;
    private const FONT_REGULAR = 'Helvetica';
    private const FONT_BOLD = 'Helvetica-Bold';

    public function generate(Summary $summary, ?Document $document = null): string
    {
        $identifier = $summary->id ?: ('draft_' . now()->format('YmdHis'));
        $path = 'reports/justconnect_summary_' . $identifier . '.pdf';
        Storage::disk('local')->put($path, $this->buildPdf($summary, $document));

        return $path;
    }

    private function buildPdf(Summary $summary, ?Document $document): string
    {
        $pages = $this->renderPages($this->reportBlocks($summary, $document));
        $pageCount = count($pages);
        $header = $this->headerStream($summary, $document);

        $objects = [];
        $pageObjectNumbers = [];
        $contentObjectNumbers = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids __KIDS__ /Count ' . $pageCount . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /' . self::FONT_REGULAR . ' /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /' . self::FONT_BOLD . ' /Encoding /WinAnsiEncoding >>';

        $nextObject = 5;
        foreach ($pages as $page) {
            $pageObjectNumbers[] = $nextObject++;
            $contentObjectNumbers[] = $nextObject++;
        }

        foreach ($pages as $index => $pageStream) {
            $pageNumber = $index + 1;
            $pageObject = $pageObjectNumbers[$index];
            $contentObject = $contentObjectNumbers[$index];
            $stream = $header . $pageStream . $this->footerStream($pageNumber, $pageCount);

            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
            $objects[$contentObject] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        $kids = '[' . implode(' ', array_map(fn ($number) => $number . ' 0 R', $pageObjectNumbers)) . ']';
        $objects[2] = str_replace('__KIDS__', $kids, $objects[2]);

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) ($offsets[$i] ?? 0), 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function reportBlocks(Summary $summary, ?Document $document): array
    {
        $entities = json_decode($summary->nlp_entities ?? '[]', true);
        $parties = json_decode($summary->parties ?? '[]', true);
        $panels = json_decode($summary->structured_panels ?? '[]', true) ?: [];
        $passages = json_decode($summary->supporting_passages ?? '[]', true) ?: [];
        $isLegalProfessionalSummary = ($summary->summary_type ?: 'general_user') === 'legal_professional';
        $executiveSummary = $this->cleanOpeningTransition(trim((string) $summary->executive_summary));
        $professionalSummary = $this->cleanOpeningTransition(trim((string) ($summary->professional_summary ?: $summary->executive_summary)));
        $citizenSummary = $this->cleanOpeningTransition(trim((string) $summary->citizen_summary));

        $entityValues = array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            array_merge(
                is_array($entities['persons'] ?? null) ? $entities['persons'] : [],
                is_array($entities['organisations'] ?? null) ? $entities['organisations'] : [],
                is_array($parties) ? $parties : []
            )
        ))));

        $blocks = $this->roleBasedSummaryBlocks(
            $summary,
            $panels,
            $isLegalProfessionalSummary,
            $executiveSummary,
            $professionalSummary,
            $citizenSummary
        );

        $blocks[] = ['type' => 'heading', 'text' => 'DOCUMENT DETAILS'];
        foreach ([
            'DOCUMENT TYPE' => $summary->document_type ?: 'Not available',
            'REFERENCE NO.' => $summary->case_number ?: 'Not available',
            'DATE OF JUDGMENT' => $summary->date_of_document ?: 'Not available',
            'COURT' => $summary->court ?: 'Not available',
            'JUDGE' => $summary->judge ?: 'Not available',
            'ENTITIES INVOLVED' => !empty($entityValues) ? implode(', ', array_slice($entityValues, 0, 8)) : 'Not available',
        ] as $label => $value) {
            $blocks[] = ['type' => 'kv', 'label' => $label, 'text' => (string) $value];
        }

        if ($isLegalProfessionalSummary && $passages !== []) {
            $blocks[] = ['type' => 'heading', 'text' => 'SUPPORTING PASSAGES'];
            foreach (array_slice($passages, 0, 4) as $passage) {
                $blocks[] = [
                    'type' => 'paragraph',
                    'text' => '[Page ' . ($passage['page'] ?? '?') . '] ' . trim((string) ($passage['text'] ?? '')),
                ];
            }
        }

        return $blocks;
    }

    private function renderPages(array $blocks): array
    {
        $pages = [''];
        $pageIndex = 0;
        $y = self::TOP_Y;
        $bodyWidth = self::PAGE_WIDTH - (self::MARGIN_X * 2);

        $newPage = function () use (&$pages, &$pageIndex, &$y): void {
            $pageIndex++;
            $pages[$pageIndex] = '';
            $y = self::TOP_Y;
        };

        $ensureSpace = function (float $height) use (&$y, $newPage): void {
            if ($y - $height < self::BOTTOM_Y) {
                $newPage();
            }
        };

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'heading') {
                $ensureSpace(38);
                $y -= 6;
                $pages[$pageIndex] .= $this->headingLine((string) $block['text'], $y);
                $y -= 28;
                continue;
            }

            if ($type === 'subheading') {
                $ensureSpace(24);
                $pages[$pageIndex] .= $this->textAt(mb_strtoupper((string) $block['text']), self::MARGIN_X, $y, 9.2, true, [42, 126, 75]);
                $y -= 17;
                continue;
            }

            if ($type === 'metric_grid') {
                $ensureSpace(58);
                $items = $block['items'] ?? [];
                $gap = 10;
                $cardWidth = ($bodyWidth - ($gap * 2)) / 3;
                foreach (array_slice((array) $items, 0, 3) as $index => $item) {
                    $x = self::MARGIN_X + (($cardWidth + $gap) * $index);
                    $pages[$pageIndex] .= $this->rect($x, $y - 42, $cardWidth, 46, [248, 250, 247], [222, 229, 222]);
                    $pages[$pageIndex] .= $this->textAt((string) ($item['label'] ?? ''), $x + 8, $y - 11, 7.2, true, [102, 113, 105]);
                    $pages[$pageIndex] .= $this->textAt((string) ($item['value'] ?? ''), $x + 8, $y - 29, 11.2, true, [26, 71, 49]);
                }
                $y -= 60;
                continue;
            }

            if ($type === 'kv') {
                $ensureSpace(28);
                $pages[$pageIndex] .= $this->textAt((string) ($block['label'] ?? ''), self::MARGIN_X, $y, 8.2, true, [102, 113, 105]);
                $lines = $this->linesForWidth((string) ($block['text'] ?? ''), self::BODY_FONT_SIZE, $bodyWidth - 150);
                foreach ($lines as $offset => $line) {
                    $isLast = $offset === count($lines) - 1;
                    $pages[$pageIndex] .= $this->lineText($line, self::MARGIN_X + 150, $y - ($offset * self::BODY_LEADING), self::BODY_FONT_SIZE, $bodyWidth - 150, false, $isLast);
                }
                $y -= max(19, count($lines) * self::BODY_LEADING);
                continue;
            }

            if ($type === 'bullet') {
                $lines = $this->linesForWidth((string) $block['text'], self::BODY_FONT_SIZE, $bodyWidth - 18);
                $ensureSpace(max(24, count($lines) * self::BODY_LEADING + 6));
                $pages[$pageIndex] .= $this->textAt('•', self::MARGIN_X, $y, self::BODY_FONT_SIZE, true, [26, 71, 49]);
                foreach ($lines as $index => $line) {
                    $isLast = $index === count($lines) - 1;
                    $pages[$pageIndex] .= $this->lineText($line, self::MARGIN_X + 18, $y - ($index * self::BODY_LEADING), self::BODY_FONT_SIZE, $bodyWidth - 18, false, $isLast);
                }
                $y -= count($lines) * self::BODY_LEADING + 4;
                continue;
            }

            $paragraphs = $this->paragraphs((string) ($block['text'] ?? ''));
            foreach ($paragraphs as $paragraph) {
                $lines = $this->linesForWidth($paragraph, self::BODY_FONT_SIZE, $bodyWidth);
                foreach ($lines as $index => $line) {
                    $ensureSpace(self::BODY_LEADING + 2);
                    $isLast = $index === count($lines) - 1;
                    $pages[$pageIndex] .= $this->lineText($line, self::MARGIN_X, $y, self::BODY_FONT_SIZE, $bodyWidth, false, $isLast);
                    $y -= self::BODY_LEADING;
                }
                $y -= 6;
            }
        }

        return $pages;
    }

    private function caseTitle(Summary $summary, string $documentName): string
    {
        $parts = array_filter([
            $documentName,
            $summary->case_number,
            $summary->date_of_document,
        ]);

        return preg_replace('/\s+/u', ' ', implode(' | ', $parts)) ?: 'Legal Summary';
    }

    private function paragraphs(string $text): array
    {
        return array_values(array_filter(array_map(
            fn ($paragraph) => trim((string) preg_replace('/\s+/u', ' ', $paragraph)),
            preg_split("/\n{2,}/u", $this->pdfSafeText($text), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
    }

    private function listItems(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(function ($item): string {
                if (is_array($item)) {
                    return trim((string) ($item['text'] ?? $item['title'] ?? $item['value'] ?? ''));
                }

                return trim((string) $item);
            }, $value)));
        }

        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => trim((string) preg_replace('/^[-*]\s*/', '', $item)),
            preg_split('/\n+|;\s+/u', $text) ?: []
        )));
    }

    private function roleBasedSummaryBlocks(
        Summary $summary,
        array $panels,
        bool $isLegalProfessionalSummary,
        string $executiveSummary,
        string $professionalSummary,
        string $citizenSummary
    ): array {
        $modeSections = is_array($panels['mode_sections'] ?? null) ? $panels['mode_sections'] : [];

        if ($this->isLegislativeDocumentType((string) ($summary->document_type ?? ''))) {
            $sections = $modeSections !== []
                ? $modeSections
                : [
                    'Summary' => $this->bestSummaryText($isLegalProfessionalSummary ? $professionalSummary : $citizenSummary, $executiveSummary),
                    'Document Type' => $summary->document_type ?? 'Legislative Instrument',
                    'Legislative Subject' => $summary->key_findings,
                    'Legal Effect' => $summary->outcome,
                    'What This Means' => $summary->practical_implications,
                ];

            return $this->summarySectionBlocks(
                $isLegalProfessionalSummary ? 'LEGISLATIVE ANALYSIS' : 'LEGISLATIVE SUMMARY',
                $sections
            );
        }

        if ($isLegalProfessionalSummary) {
            $sections = [
                'Summary' => $this->bestSummaryText($modeSections['Summary'] ?? null, $professionalSummary, $executiveSummary),
                'Document Type' => $modeSections['Document Type'] ?? $summary->document_type ?? 'Legal Document',
                'Citation / Court Details' => $modeSections['Citation / Court Details'] ?? implode(' | ', array_filter([
                    $summary->case_number,
                    $summary->court,
                    $summary->judge,
                    $summary->date_of_document,
                ])),
                'Facts' => $this->bestSectionText($modeSections['Facts'] ?? null, $summary->key_findings ?: $professionalSummary, 45),
                'Legal Issues' => $modeSections['Legal Issues'] ?? $panels['key_legal_issues'] ?? [],
                'Holding / Decision' => $this->bestSectionText($modeSections['Holding / Decision'] ?? null, $summary->outcome, 8),
                'Ratio Decidendi' => $this->bestSectionText($modeSections['Ratio Decidendi'] ?? null, $summary->legal_principles, 12),
                'Orders / Remedies' => $modeSections['Orders / Remedies'] ?? $this->listItems($summary->key_obligations ?: $summary->practical_implications),
                'Authorities Cited' => $modeSections['Authorities Cited'] ?? $panels['important_legal_references'] ?? [],
                'Cited Instruments' => $modeSections['Cited Instruments'] ?? $panels['cited_instruments'] ?? [],
            ];

            return $this->summarySectionBlocks('LEGAL PROFESSIONAL SUMMARY', $sections);
        }

        $sections = [
            'Summary' => $this->bestSummaryText($modeSections['Summary'] ?? null, $citizenSummary, $executiveSummary),
            'Document Overview' => $this->bestSectionText(
                $modeSections['Document Overview'] ?? $modeSections['What Happened'] ?? null,
                $summary->key_findings ?: $citizenSummary,
                45
            ),
            'Main Issue' => $modeSections['Main Issue'] ?? $panels['key_legal_issues'] ?? $this->listItems($summary->key_findings),
            'Decision / Outcome' => $this->bestSectionText($modeSections['Decision / Outcome'] ?? null, $summary->outcome, 8),
            'What This Means' => $this->bestSectionText($modeSections['What This Means'] ?? null, $summary->practical_implications, 15),
        ];

        return $this->summarySectionBlocks('GENERAL USER SUMMARY', $sections);
    }

    private function summarySectionBlocks(string $heading, array $sections): array
    {
        $blocks = [['type' => 'heading', 'text' => $heading]];

        foreach ($sections as $title => $value) {
            $text = trim($this->sectionText($value));
            $items = is_array($value) ? $this->listItems($value) : [];
            if ($text === '' && $items === []) {
                continue;
            }

            $blocks[] = ['type' => 'subheading', 'text' => (string) $title];

            if ($items !== []) {
                foreach ($items as $item) {
                    $blocks[] = ['type' => 'bullet', 'text' => $item];
                }
                continue;
            }

            foreach ($this->paragraphs($text) as $paragraph) {
                $blocks[] = ['type' => 'paragraph', 'text' => $paragraph];
            }
        }

        return $blocks;
    }

    private function bestSummaryText(mixed ...$values): string
    {
        $candidates = array_values(array_filter(array_map(
            fn ($value) => trim($this->sectionText($value)),
            $values
        )));

        usort($candidates, fn ($a, $b) => $this->wordCount($b) <=> $this->wordCount($a));

        return $candidates[0] ?? 'No summary available yet.';
    }

    private function bestSectionText(mixed $savedValue, mixed $fallbackValue, int $minimumSavedWords = 20): string
    {
        $saved = trim($this->sectionText($savedValue));
        $fallback = trim($this->sectionText($fallbackValue));

        if ($saved === '') {
            return $fallback;
        }

        if ($fallback === '') {
            return $saved;
        }

        return $this->wordCount($saved) < $minimumSavedWords && $this->wordCount($fallback) > $this->wordCount($saved)
            ? $fallback
            : $saved;
    }

    private function sectionText(mixed $value): string
    {
        if (is_array($value)) {
            return implode("\n", $this->listItems($value));
        }

        return $this->cleanOpeningTransition(trim((string) $value));
    }

    private function cleanOpeningTransition(string $text): string
    {
        return trim((string) preg_replace('/^\s*(however|nevertheless|nonetheless|therefore|thus|consequently|accordingly),\s+/i', '', $text));
    }

    private function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function isLegislativeDocumentType(string $docType): bool
    {
        return in_array($docType, ['Act', 'Bill', 'Statutory Instrument'], true);
    }

    private function linesForWidth(string $text, float $fontSize, float $width): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($this->textWidth($candidate, $fontSize) <= $width) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    private function headingLine(string $text, float $y): string
    {
        $stream = $this->line(self::MARGIN_X, $y - 9, self::PAGE_WIDTH - self::MARGIN_X, $y - 9, [226, 231, 224]);
        $stream .= $this->textAt(mb_strtoupper($text), self::MARGIN_X, $y, 11.4, true, [26, 71, 49]);

        return $stream;
    }

    private function headerStream(Summary $summary, ?Document $document): string
    {
        $documentName = $document?->original_name ?: ('Summary #' . $summary->id);
        $documentType = (string) ($summary->document_type ?: 'Legal Summary');
        $reference = trim((string) ($summary->case_number ?: 'Reference not available'));
        $dateOfDocument = trim((string) ($summary->date_of_document ?: 'Date not available'));
        $court = trim((string) ($summary->court ?: 'Court not available'));
        $mode = str_replace('_', ' ', (string) ($summary->summary_type ?: 'general_user'));
        $generated = now()->format('j M Y');

        $stream = $this->rect(0, 714, self::PAGE_WIDTH, 128, [26, 71, 49]);
        $stream .= $this->rect(0, 707, self::PAGE_WIDTH, 7, [42, 126, 75]);
        $stream .= $this->textAt('JustConnect', self::MARGIN_X, 804, 18.5, true, [255, 255, 255]);
        $stream .= $this->textAt('Smarter Legal Decisions Powered by NLP', self::MARGIN_X, 786, 8.8, false, [219, 235, 224]);
        $stream .= $this->textAt('CONFIDENTIAL NLP SUMMARY', self::PAGE_WIDTH - self::MARGIN_X - 133, 806, 8.2, true, [219, 235, 224]);
        $stream .= $this->textAt('Generated ' . $generated, self::PAGE_WIDTH - self::MARGIN_X - 87, 789, 8.2, false, [219, 235, 224]);

        $titleLines = array_map(
            fn ($line) => $this->fitLine($line, 11.6, 318),
            array_slice($this->linesForWidth($documentName, 11.6, 318), 0, 2)
        );
        foreach ($titleLines as $index => $line) {
            $stream .= $this->textAt($line, self::MARGIN_X, 758 - ($index * 15), 11.6, true, [255, 255, 255]);
        }

        $infoX = self::MARGIN_X + 352;
        $stream .= $this->headerInfo('TYPE', $documentType, $infoX, 759, 118, 38);
        $stream .= $this->headerInfo('REF', $reference, $infoX, 741, 118, 38);
        $stream .= $this->headerInfo('DATE', $dateOfDocument, $infoX, 723, 118, 38);
        $stream .= $this->headerInfo('COURT', $court, self::MARGIN_X, 724, 230, 42);
        $stream .= $this->headerInfo('MODE', mb_convert_case($mode, MB_CASE_TITLE, 'UTF-8'), self::MARGIN_X + 258, 724, 86, 40);

        return $stream;
    }

    private function headerInfo(string $label, string $value, float $x, float $y, float $maxWidth = 128, float $labelWidth = 34): string
    {
        $valueLine = $this->fitLine($this->linesForWidth($value, 8.2, $maxWidth)[0] ?? '', 8.2, $maxWidth);

        $stream = $this->textAt($label, $x, $y, 6.3, true, [174, 214, 187]);
        $stream .= $this->textAt($valueLine, $x + $labelWidth, $y, 8.2, false, [255, 255, 255]);

        return $stream;
    }

    private function fitLine(string $text, float $fontSize, float $width): string
    {
        if ($this->textWidth($text, $fontSize) <= $width) {
            return $text;
        }

        $suffix = '...';
        while ($text !== '' && $this->textWidth($text . $suffix, $fontSize) > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text) . $suffix;
    }

    private function lineText(string $text, float $x, float $y, float $fontSize, float $width, bool $bold = false, bool $isLast = false): string
    {
        $wordCount = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $wordSpacing = 0.0;

        // PDF text justification: expand word spacing on non-final paragraph lines.
        if (!$isLast && $wordCount > 1) {
            $naturalWidth = $this->textWidth($text, $fontSize);
            $wordSpacing = max(0, min(3.5, ($width - $naturalWidth) / ($wordCount - 1)));
        }

        return $this->textAt($text, $x, $y, $fontSize, $bold, [42, 52, 45], $wordSpacing);
    }

    private function textCentered(string $text, float $y, float $fontSize, bool $bold, array $rgb): string
    {
        $width = $this->textWidth($text, $fontSize);
        $x = max(self::MARGIN_X, (self::PAGE_WIDTH - $width) / 2);

        return $this->textAt($text, $x, $y, $fontSize, $bold, $rgb);
    }

    private function textAt(string $text, float $x, float $y, float $fontSize, bool $bold, array $rgb, float $wordSpacing = 0.0): string
    {
        [$r, $g, $b] = array_map(fn ($value) => round($value / 255, 4), $rgb);
        $font = $bold ? 'F2' : 'F1';

        return "BT\n{$r} {$g} {$b} rg\n/{$font} " . $this->num($fontSize) . " Tf\n" . $this->num($wordSpacing) . " Tw\n1 0 0 1 " . $this->num($x) . ' ' . $this->num($y) . " Tm\n(" . $this->escapePdfString($text) . ") Tj\n0 Tw\nET\n";
    }

    private function rect(float $x, float $y, float $width, float $height, array $fill, ?array $stroke = null): string
    {
        [$fr, $fg, $fb] = array_map(fn ($value) => round($value / 255, 4), $fill);
        $stream = "{$fr} {$fg} {$fb} rg\n";

        if ($stroke) {
            [$sr, $sg, $sb] = array_map(fn ($value) => round($value / 255, 4), $stroke);
            $stream .= "{$sr} {$sg} {$sb} RG\n0.6 w\n" . $this->num($x) . ' ' . $this->num($y) . ' ' . $this->num($width) . ' ' . $this->num($height) . " re\nB\n";
        } else {
            $stream .= $this->num($x) . ' ' . $this->num($y) . ' ' . $this->num($width) . ' ' . $this->num($height) . " re\nf\n";
        }

        return $stream;
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $rgb): string
    {
        [$r, $g, $b] = array_map(fn ($value) => round($value / 255, 4), $rgb);

        return "{$r} {$g} {$b} RG\n0.6 w\n" . $this->num($x1) . ' ' . $this->num($y1) . " m\n" . $this->num($x2) . ' ' . $this->num($y2) . " l\nS\n";
    }

    private function footerStream(int $pageNumber, int $pageCount): string
    {
        $stream = $this->line(self::MARGIN_X, 42, self::PAGE_WIDTH - self::MARGIN_X, 42, [226, 231, 224]);
        $stream .= $this->textAt('JustConnect - Smarter Legal Decisions Powered by NLP - Confidential', self::MARGIN_X, 28, 8, false, [102, 113, 105]);
        $stream .= $this->textAt('Page ' . $pageNumber . ' of ' . $pageCount, self::PAGE_WIDTH - self::MARGIN_X - 58, 28, 8, false, [102, 113, 105]);

        return $stream;
    }

    private function textWidth(string $text, float $fontSize): float
    {
        $width = 0.0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($chars as $char) {
            $width += match (true) {
                $char === ' ' => 0.26,
                preg_match('/[ilI.,;:\'!|]/u', $char) === 1 => 0.24,
                preg_match('/[mwMW@#%&]/u', $char) === 1 => 0.82,
                preg_match('/[A-Z0-9]/u', $char) === 1 => 0.58,
                default => 0.5,
            };
        }

        return $width * $fontSize;
    }

    private function pdfSafeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(['•', '–', '—', '“', '”', '‘', '’'], ['-', '-', '-', '"', '"', "'", "'"], $text);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $converted !== false ? $converted : preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text);
    }

    private function escapePdfString(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $this->pdfSafeText($text)
        );
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
