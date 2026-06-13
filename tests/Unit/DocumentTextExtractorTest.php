<?php

namespace Tests\Unit;

use App\Services\Documents\DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTextExtractorTest extends TestCase
{
    public function test_pdf_extraction_prefers_ghostscript_before_the_pdf_parser(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('knowledge/sample.pdf', 'placeholder');

        $extractor = new class extends DocumentTextExtractor
        {
            public array $calls = [];

            protected function extractPdfWithPdftotext(string $path): array
            {
                $this->calls[] = 'pdftotext';

                return [];
            }

            protected function extractPdfWithGhostscriptText(string $path): array
            {
                $this->calls[] = 'ghostscript_txtwrite';

                return [[
                    'page' => 1,
                    'text' => 'This legal knowledge document contains enough meaningful words to stop the extractor before it reaches the parser fallback.',
                ]];
            }

            protected function extractPdfWithParser(string $path): array
            {
                $this->calls[] = 'pdf_parser';

                return [[
                    'page' => 1,
                    'text' => 'Parser fallback should not be needed here.',
                ]];
            }

            protected function extractPdfWithOcr(string $path): array
            {
                $this->calls[] = 'ocr_fallback';

                return [[
                    'page' => 1,
                    'text' => 'OCR fallback should not be needed here.',
                ]];
            }
        };

        $pages = $extractor->extract('knowledge/sample.pdf', 'local', 'application/pdf');

        $this->assertSame(['pdftotext', 'ghostscript_txtwrite'], $extractor->calls);
        $this->assertSame(
            'This legal knowledge document contains enough meaningful words to stop the extractor before it reaches the parser fallback.',
            $pages[0]['text'] ?? null
        );
    }
}
