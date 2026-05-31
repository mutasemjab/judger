<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;

class DocumentTextExtractor
{
    public function extract(string $filePath, string $disk = 'local'): array
    {
        $absolutePath = Storage::disk($disk)->path($filePath);
        $mimeType = mime_content_type($absolutePath) ?: '';

        if (str_contains($mimeType, 'pdf') || str_ends_with(strtolower($filePath), '.pdf')) {
            return $this->extractPdf($absolutePath);
        }

        if (
            str_contains($mimeType, 'wordprocessingml') ||
            str_ends_with(strtolower($filePath), '.docx')
        ) {
            return $this->extractDocx($absolutePath);
        }

        if (str_contains($mimeType, 'text') || str_ends_with(strtolower($filePath), '.txt')) {
            return $this->extractText($absolutePath);
        }

        return [['page' => 1, 'text' => '']];
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
                $result[] = [
                    'page' => $index + 1,
                    'text' => trim($page->getText()),
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [['page' => 1, 'text' => '']];
        }
    }

    private function extractDocx(string $path): array
    {
        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
            $fullText = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $fullText .= $element->getText() . "\n";
                    }
                }
            }
            return [['page' => 1, 'text' => $fullText]];
        } catch (\Throwable $e) {
            return [['page' => 1, 'text' => '']];
        }
    }

    private function extractText(string $path): array
    {
        $text = file_get_contents($path) ?: '';
        return [['page' => 1, 'text' => $text]];
    }
}
