<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class DocumentTextExtractionService
{
    public function extract(UploadedFile $file): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath() ?: $file->path();

        $text = match ($extension) {
            'txt' => (string) file_get_contents($path),
            'docx' => $this->extractDocx($path),
            'doc' => $this->extractDoc($path),
            default => '',
        };

        $text = $this->normalise($text);

        return [
            'text' => $text,
            'word_count' => $this->wordCount($text),
            'page_count' => null,
            'extractor' => $extension,
        ];
    }

    private function extractDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $parts = [
            'word/document.xml',
            'word/footnotes.xml',
            'word/endnotes.xml',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $parts[] = "word/header{$i}.xml";
            $parts[] = "word/footer{$i}.xml";
        }

        $chunks = [];
        foreach ($parts as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) {
                continue;
            }

            $chunks[] = $this->textFromWordXml($xml);
        }

        $zip->close();

        return implode("\n\n", array_filter($chunks));
    }

    private function textFromWordXml(string $xml): string
    {
        $xml = preg_replace('/<w:tab\b[^>]*\/>/u', "\t", $xml);
        $xml = preg_replace('/<w:br\b[^>]*\/>/u', "\n", (string) $xml);
        $xml = preg_replace('/<\/w:p>/u', "\n", (string) $xml);
        $xml = preg_replace('/<\/w:tr>/u', "\n", (string) $xml);
        $xml = preg_replace('/<\/w:tc>/u', "\t", (string) $xml);
        $text = strip_tags((string) $xml);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function extractDoc(string $path): string
    {
        if (class_exists('COM')) {
            $text = $this->extractDocWithWordCom($path);
            if ($text !== '') {
                return $text;
            }
        }

        return $this->extractDocBinaryText($path);
    }

    private function extractDocWithWordCom(string $path): string
    {
        try {
            $word = new \COM('Word.Application');
            $word->Visible = false;
            $document = $word->Documents->Open($path, false, true);
            $text = (string) $document->Content->Text;
            $document->Close(false);
            $word->Quit();

            return $text;
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractDocBinaryText(string $path): string
    {
        $bytes = (string) file_get_contents($path);
        if ($bytes === '') {
            return '';
        }

        $utf16 = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE');
        preg_match_all('/[\p{L}\p{N}\p{P}\p{S} ][\p{L}\p{N}\p{P}\p{S} \t]{3,}/u', (string) $utf16, $wideMatches);
        preg_match_all('/[A-Za-z0-9][A-Za-z0-9 ,.;:\'"!?()\/\-\r\n]{3,}/', $bytes, $asciiMatches);

        $chunks = array_merge($wideMatches[0] ?? [], $asciiMatches[0] ?? []);
        $chunks = array_values(array_filter(array_map(
            fn ($chunk) => trim((string) preg_replace('/\s+/u', ' ', $chunk)),
            $chunks
        ), static fn ($chunk) => mb_strlen($chunk) >= 12 && !preg_match('/[\x00-\x08\x0E-\x1F]/', $chunk)));

        return implode("\n", array_unique($chunks));
    }

    private function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\u{00AD}", '', $text);
        $text = preg_replace('/[ \x{00A0}]{2,}/u', ' ', (string) $text);
        $text = preg_replace('/[ \t]+\n/u', "\n", (string) $text);
        $text = preg_replace('/\n{3,}/u', "\n\n", (string) $text);

        return trim((string) $text);
    }

    private function wordCount(string $text): int
    {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'-]*/u', $text, $matches);

        return count($matches[0] ?? []);
    }
}
