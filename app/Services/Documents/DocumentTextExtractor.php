<?php

namespace App\Services\Documents;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;
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
            str_contains($mimeType, 'msword') ||
            str_ends_with($lowerPath, '.doc')
        ) {
            return $this->extractLegacyDoc($absolutePath);
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
        $strategies = [
            'pdftotext' => fn (): array => $this->extractPdfWithPdftotext($path),
            'ghostscript_txtwrite' => fn (): array => $this->extractPdfWithGhostscriptText($path),
            'pdf_parser' => fn (): array => $this->extractPdfWithParser($path),
            'ocr_fallback' => fn (): array => $this->extractPdfWithOcr($path),
        ];

        foreach ($strategies as $strategyName => $strategy) {
            try {
                $pages = $strategy();

                if ($this->hasMeaningfulText($pages)) {
                    $this->logExtractionResult($path, $strategyName, $pages, 'success');

                    return $pages;
                }

                $this->logExtractionResult($path, $strategyName, $pages, 'empty');
            } catch (Throwable) {
                $this->logExtractionResult($path, $strategyName, [], 'failed');

                continue;
            }
        }

        Log::warning('knowledge.extractor.pdf_failed', [
            'file' => basename($path),
        ]);

        return [['page' => 1, 'text' => '']];
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

    private function extractLegacyDoc(string $path): array
    {
        $strategies = [
            'antiword' => ['antiword', $path],
            'catdoc' => ['catdoc', '-d', 'utf-8', $path],
            'textutil' => ['textutil', '-convert', 'txt', '-stdout', $path],
        ];

        foreach ($strategies as $strategyName => $command) {
            if (! $this->commandExists($command[0])) {
                continue;
            }

            try {
                $text = $this->runCommand($command, throwOnFailure: false);
                $normalized = $this->normalizeBlockText((string) $text);

                if ($normalized !== '') {
                    Log::info('knowledge.extractor.legacy_doc_strategy', [
                        'file' => basename($path),
                        'strategy' => $strategyName,
                        'status' => 'success',
                        'characters' => mb_strlen($normalized),
                    ]);

                    return [['page' => 1, 'text' => $normalized]];
                }
            } catch (Throwable) {
                Log::info('knowledge.extractor.legacy_doc_strategy', [
                    'file' => basename($path),
                    'strategy' => $strategyName,
                    'status' => 'failed',
                ]);
            }
        }

        Log::warning('knowledge.extractor.legacy_doc_failed', [
            'file' => basename($path),
        ]);

        return [['page' => 1, 'text' => '']];
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
        $text = $this->normalizeUnicodeText($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? $text;
        $text = str_replace('ـ', '', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizeInlineText(string $text): string
    {
        $text = $this->normalizeUnicodeText($text);
        $text = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? $text;
        $text = str_replace('ـ', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    protected function extractPdfWithParser(string $path): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        $pages = $pdf->getPages();

        if (empty($pages)) {
            return [[
                'page' => 1,
                'text' => $this->normalizeBlockText((string) $pdf->getText()),
            ]];
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

        return $result;
    }

    protected function extractPdfWithPdftotext(string $path): array
    {
        if (! $this->commandExists('pdftotext')) {
            return [];
        }

        $outputFile = tempnam(sys_get_temp_dir(), 'kb-pdftotext-');

        if ($outputFile === false) {
            return [];
        }

        try {
            // Do NOT use -layout: it reverses Arabic RTL word order by placing
            // text at physical X-column positions, which scrambles right-to-left lines.
            $this->runCommand([
                'pdftotext',
                '-enc', 'UTF-8',
                '-nopgbrk',
                $path,
                $outputFile,
            ]);

            $text = @file_get_contents($outputFile) ?: '';

            return $this->pagesFromFlatText($text);
        } finally {
            @unlink($outputFile);
        }
    }

    protected function extractPdfWithGhostscriptText(string $path): array
    {
        if (! $this->commandExists('gs')) {
            return [];
        }

        $tempDir = $this->makeTempDirectory('kb-gs-text-');
        $outputPattern = $tempDir.DIRECTORY_SEPARATOR.'page-%03d.txt';

        try {
            $this->runCommand([
                'gs',
                '-q',
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-sDEVICE=txtwrite',
                '-sOutputFile='.$outputPattern,
                $path,
            ]);

            return $this->pagesFromGeneratedFiles($tempDir, '*.txt');
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    protected function extractPdfWithOcr(string $path): array
    {
        if (! $this->commandExists('gs') || ! $this->commandExists('tesseract')) {
            return [];
        }

        $language = $this->resolveTesseractLanguage();

        if ($language === null) {
            return [];
        }

        $tempDir = $this->makeTempDirectory('kb-ocr-');
        $imagePattern = $tempDir.DIRECTORY_SEPARATOR.'page-%03d.png';

        try {
            $this->runCommand([
                'gs',
                '-q',
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-sDEVICE=png16m',
                '-r180',
                '-sOutputFile='.$imagePattern,
                $path,
            ]);

            $images = glob($tempDir.DIRECTORY_SEPARATOR.'page-*.png') ?: [];
            sort($images);

            $pages = [];

            foreach ($images as $index => $imagePath) {
                $text = $this->runCommand([
                    'tesseract',
                    $imagePath,
                    'stdout',
                    '-l',
                    $language,
                    '--psm',
                    '3',
                ]);

                $normalized = $this->normalizeBlockText($text);

                if ($normalized === '') {
                    continue;
                }

                $pages[] = [
                    'page' => $index + 1,
                    'text' => $normalized,
                ];
            }

            return $pages;
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    private function pagesFromFlatText(string $text): array
    {
        $pages = [];
        $chunks = preg_split("/\f/u", $text) ?: [$text];

        foreach ($chunks as $index => $chunk) {
            $normalized = $this->normalizeBlockText($chunk);

            if ($normalized === '') {
                continue;
            }

            $pages[] = [
                'page' => $index + 1,
                'text' => $normalized,
            ];
        }

        return $pages;
    }

    private function pagesFromGeneratedFiles(string $directory, string $pattern): array
    {
        $files = glob($directory.DIRECTORY_SEPARATOR.$pattern) ?: [];
        sort($files);

        $pages = [];

        foreach ($files as $index => $file) {
            $text = @file_get_contents($file) ?: '';
            $normalized = $this->normalizeBlockText($text);

            if ($normalized === '') {
                continue;
            }

            $pages[] = [
                'page' => $index + 1,
                'text' => $normalized,
            ];
        }

        return $pages;
    }

    private function hasMeaningfulText(array $pages): bool
    {
        $text = $this->normalizeBlockText(implode("\n\n", array_map(
            fn (array $page): string => (string) ($page['text'] ?? ''),
            $pages
        )));

        if ($text === '') {
            return false;
        }

        preg_match_all('/[\p{L}\p{N}]/u', $text, $matches);
        $lettersAndNumbers = count($matches[0] ?? []);
        $words = preg_split('/\s+/u', $text) ?: [];
        $meaningfulWords = count(array_filter($words, fn (string $word): bool => $word !== ''));

        return $lettersAndNumbers >= 40 && $meaningfulWords >= 10;
    }

    private function resolveTesseractLanguage(): ?string
    {
        static $language = false;

        if ($language !== false) {
            return $language ?: null;
        }

        $output = $this->runCommand(['tesseract', '--list-langs'], throwOnFailure: false);

        if ($output === null) {
            $language = '';

            return null;
        }

        $available = array_map('trim', preg_split('/\R/u', $output) ?: []);

        $language = match (true) {
            in_array('ara', $available, true) && in_array('eng', $available, true) => 'ara+eng',
            in_array('ara', $available, true) => 'ara',
            in_array('eng', $available, true) => 'eng',
            default => '',
        };

        if ($language === 'eng') {
            Log::warning('knowledge.extractor.ocr_arabic_missing', [
                'available_languages' => $available,
            ]);
        }

        return $language ?: null;
    }

    protected function commandExists(string $command): bool
    {
        static $cache = [];

        if (array_key_exists($command, $cache)) {
            return $cache[$command];
        }

        $result = $this->runCommand(['sh', '-lc', 'command -v '.escapeshellarg($command)], throwOnFailure: false);

        return $cache[$command] = is_string($result) && trim($result) !== '';
    }

    protected function runCommand(array $command, bool $throwOnFailure = true): ?string
    {
        if (! class_exists(Process::class) || ! function_exists('proc_open')) {
            return null;
        }

        $escapedCommand = implode(' ', array_map('escapeshellarg', $command));
        $timeout = max(5, (int) config('ai.document_extraction_command_timeout', 90));
        $process = new Process($command, null, null, null, $timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            if ($throwOnFailure) {
                throw new RuntimeException('Command timed out: '.$escapedCommand, previous: $exception);
            }

            return null;
        }

        if (! $process->isSuccessful()) {
            if ($throwOnFailure) {
                $stderr = trim($process->getErrorOutput());

                throw new RuntimeException($stderr !== '' ? $stderr : ('Command failed: '.$escapedCommand));
            }

            return null;
        }

        return $process->getOutput();
    }

    private function makeTempDirectory(string $prefix): string
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $directory = $base.DIRECTORY_SEPARATOR.$prefix.bin2hex(random_bytes(8));

        if (! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create temporary directory for document extraction.');
        }

        return $directory;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function normalizeUnicodeText(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);

            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return $text;
    }

    private function logExtractionResult(string $path, string $strategyName, array $pages, string $status): void
    {
        $nonEmptyPages = array_values(array_filter($pages, function (array $page): bool {
            return trim((string) ($page['text'] ?? '')) !== '';
        }));

        $characterCount = mb_strlen(implode("\n", array_map(
            fn (array $page): string => (string) ($page['text'] ?? ''),
            $nonEmptyPages
        )));

        Log::info('knowledge.extractor.pdf_strategy', [
            'file' => basename($path),
            'strategy' => $strategyName,
            'status' => $status,
            'pages' => count($pages),
            'non_empty_pages' => count($nonEmptyPages),
            'characters' => $characterCount,
        ]);
    }
}
