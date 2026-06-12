<?php

namespace App\Services\Documents;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;
use ZipArchive;

class DocumentTextExtractor
{
    public function extract(string $filePath, string $disk = 'local', ?string $providedMimeType = null): array
    {
        $absolutePath = Storage::disk($disk)->path($filePath);

        if (! is_file($absolutePath)) {
            throw new RuntimeException("Document file not found: {$filePath}");
        }

        $mimeType = $providedMimeType ?: (mime_content_type($absolutePath) ?: '');
        $lowerPath = strtolower($filePath);

        if (str_contains($mimeType, 'pdf') || str_ends_with($lowerPath, '.pdf')) {
            return $this->extractPdf($absolutePath);
        }

        if (
            str_contains($mimeType, 'wordprocessingml') ||
            str_ends_with($lowerPath, '.docx')
        ) {
            return $this->extractDocx($absolutePath);
        }

        if (
            str_contains($mimeType, 'presentationml') ||
            str_ends_with($lowerPath, '.pptx')
        ) {
            return $this->extractPptx($absolutePath);
        }

        if (str_contains($mimeType, 'text') || str_ends_with($lowerPath, '.txt')) {
            return $this->extractText($absolutePath);
        }

        return [['page' => 1, 'text' => '']];
    }

    public function extractFromStoragePath(string $filePath, ?string $mimeType = null, string $disk = 'local'): array
    {
        return $this->extract($filePath, $disk, $mimeType);
    }

    private function extractPdf(string $path): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($path);
            $pages = $pdf->getPages();

            if (empty($pages)) {
                return [['page' => 1, 'text' => $pdf->getText()]];
            }

            $result = [];
            foreach ($pages as $index => $page) {
                $text = $this->normalizeBlockText((string) $page->getText());

                if ($text === '') {
                    continue;
                }

                $result[] = [
                    'page' => $index + 1,
                    'text' => $text,
                ];
            }

            return $result !== [] ? $result : [['page' => 1, 'text' => $this->normalizeBlockText((string) $pdf->getText())]];
        } catch (\Throwable $e) {
            return [['page' => 1, 'text' => '']];
        }
    }

    private function extractDocx(string $path): array
    {
        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
            $pages = [];
            $pageNumber = 1;

            foreach ($phpWord->getSections() as $section) {
                $sectionBlocks = [];

                foreach ($section->getElements() as $element) {
                    $text = $this->extractTextFromPhpWordElement($element);

                    if ($text !== '') {
                        $sectionBlocks[] = $text;
                    }
                }

                $sectionText = $this->normalizeBlockText(implode("\n\n", $sectionBlocks));

                if ($sectionText === '') {
                    continue;
                }

                $pages[] = [
                    'page' => $pageNumber++,
                    'text' => $sectionText,
                ];
            }

            return $pages !== [] ? $pages : [['page' => 1, 'text' => '']];
        } catch (\Throwable $e) {
            return [['page' => 1, 'text' => '']];
        }
    }

    private function extractPptx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [['page' => 1, 'text' => '']];
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [['page' => 1, 'text' => '']];
        }

        $slides = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);

            if (! is_string($entryName)) {
                continue;
            }

            if (! preg_match('/ppt\/slides\/slide(\d+)\.xml$/', $entryName, $matches)) {
                continue;
            }

            $slides[(int) $matches[1]] = $entryName;
        }

        ksort($slides);

        $pages = [];

        foreach ($slides as $slideNumber => $entryName) {
            $xml = $zip->getFromName($entryName);

            if (! is_string($xml)) {
                continue;
            }

            $text = $this->extractTextFromPptxXml($xml);

            if ($text === '') {
                continue;
            }

            $pages[] = [
                'page' => $slideNumber,
                'text' => $text,
            ];
        }

        $zip->close();

        return $pages !== [] ? $pages : [['page' => 1, 'text' => '']];
    }

    private function extractText(string $path): array
    {
        $text = file_get_contents($path) ?: '';

        return [['page' => 1, 'text' => $this->normalizeBlockText($text)]];
    }

    private function extractTextFromPhpWordElement(mixed $element): string
    {
        $blocks = [];

        if (method_exists($element, 'getText')) {
            $text = $this->normalizeInlineText((string) $element->getText());

            if ($text !== '') {
                $blocks[] = $text;
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $childText = $this->extractTextFromPhpWordElement($child);

                if ($childText !== '') {
                    $blocks[] = $childText;
                }
            }
        }

        if (method_exists($element, 'getRows')) {
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $child) {
                        $childText = $this->extractTextFromPhpWordElement($child);

                        if ($childText !== '') {
                            $blocks[] = $childText;
                        }
                    }
                }
            }
        }

        return $this->normalizeBlockText(implode("\n", $blocks));
    }

    private function extractTextFromPptxXml(string $xml): string
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        if (! $dom->loadXML($xml)) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

        $blocks = [];

        foreach ($xpath->query('//a:t') ?: [] as $node) {
            $text = $this->normalizeInlineText((string) $node->textContent);

            if ($text !== '') {
                $blocks[] = $text;
            }
        }

        return $this->normalizeBlockText(implode("\n", $blocks));
    }

    private function normalizeBlockText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizeInlineText(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
