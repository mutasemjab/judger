<?php

namespace App\Services\Documents;

use App\Models\AiToolOutput;
use App\Models\GeneratedDocument;
use App\Models\Message;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

class GeneratedFileExportService
{
    public function exportMessage(Message $message): array
    {
        $existing = $message->metadata['download'] ?? null;
        if (is_array($existing) && $this->existsAndIsPreferred($existing)) {
            return $existing;
        }

        $download = $this->storeWordDocument(
            directory: "generated/chat_messages/user_{$message->conversation->user_id}",
            title: $message->conversation->title ?: 'judger-ai-response',
            content: $this->contentWithDisclaimer($message->content, $message->disclaimer),
            url: "/api/v1/messages/{$message->id}/download"
        );

        $metadata = $message->metadata ?? [];
        $metadata['download'] = $download;
        $message->forceFill(['metadata' => $metadata])->save();

        return $download;
    }

    public function exportAiToolOutput(AiToolOutput $output): array
    {
        $existing = $output->output['download'] ?? null;
        if (is_array($existing) && $this->existsAndIsPreferred($existing)) {
            return $existing;
        }

        $download = $this->storeWordDocument(
            directory: "generated/ai_tools/user_{$output->user_id}",
            title: $output->tool_type?->label() ?? 'judger-ai-output',
            content: $this->contentWithDisclaimer($output->content ?? '', $output->disclaimer),
            url: "/api/v1/ai-tools/{$output->id}/download"
        );

        $payload = $output->output ?? [];
        $payload['download'] = $download;
        $output->forceFill(['output' => $payload])->save();

        return $download;
    }

    public function exportGeneratedDocument(GeneratedDocument $document): array
    {
        $existingPath = $document->export_path;

        if (is_string($existingPath)
            && Str::endsWith($existingPath, '.docx')
            && Storage::disk('local')->exists($existingPath)
        ) {
            return $this->hydrateDownload(
                storagePath: $existingPath,
                fileName: basename($existingPath),
                url: "/api/v1/generated-documents/{$document->id}/download"
            );
        }

        $download = $this->storeWordDocument(
            directory: "generated/template_documents/user_{$document->user_id}",
            title: $document->title ?: 'generated-document',
            content: $this->contentWithDisclaimer($document->content, $document->disclaimer),
            url: "/api/v1/generated-documents/{$document->id}/download"
        );

        $document->forceFill(['export_path' => $download['storage_path']])->save();

        return $download;
    }

    public function publicDownloadData(?array $download): ?array
    {
        if ($download === null) {
            return null;
        }

        return Arr::except($download, ['storage_path']);
    }

    public function downloadResponse(array $download): mixed
    {
        $storagePath = $download['storage_path'] ?? null;

        if (! is_string($storagePath) || ! Storage::disk('local')->exists($storagePath)) {
            throw new RuntimeException('Generated file is not available for download.');
        }

        return Storage::disk('local')->download(
            $storagePath,
            $download['file_name'] ?? basename($storagePath),
            ['Content-Type' => $download['mime_type'] ?? $this->wordMimeType()]
        );
    }

    private function storeWordDocument(string $directory, string $title, string $content, string $url): array
    {
        $slug = Str::slug(Str::limit($title, 60, ''));
        $slug = $slug !== '' ? $slug : 'judger-export';
        $fileName = $slug . '-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(6)) . '.docx';
        $storagePath = trim($directory, '/') . '/' . $fileName;

        $absolutePath = Storage::disk('local')->path($storagePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        $this->writeWordDocument($absolutePath, $this->normalizeDocumentBody($title, $content));

        return $this->hydrateDownload($storagePath, $fileName, $url);
    }

    private function hydrateDownload(string $storagePath, string $fileName, string $url): array
    {
        return [
            'storage_path' => $storagePath,
            'file_name' => $fileName,
            'mime_type' => $this->wordMimeType(),
            'format' => 'docx',
            'extension' => 'docx',
            'available' => true,
            'url' => $url,
            'label' => 'Download Word document',
            'button_label' => 'Download DOCX',
            'action' => 'download_generated_file',
        ];
    }

    private function writeWordDocument(string $absolutePath, string $content): void
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName($this->containsArabic($content) ? 'Arial' : 'Aptos');
        $phpWord->setDefaultFontSize(11);

        $alignment = $this->containsArabic($content) ? 'right' : 'left';
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 18, 'color' => '1F2937'], ['alignment' => $alignment, 'spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 15, 'color' => '334155'], ['alignment' => $alignment, 'spaceBefore' => 180, 'spaceAfter' => 120]);
        $phpWord->addTitleStyle(3, ['bold' => true, 'size' => 13, 'color' => '475569'], ['alignment' => $alignment, 'spaceBefore' => 120, 'spaceAfter' => 80]);

        $section = $phpWord->addSection([
            'marginTop' => 900,
            'marginRight' => 900,
            'marginBottom' => 900,
            'marginLeft' => 900,
        ]);

        $paragraph = ['alignment' => $alignment, 'spaceAfter' => 120];
        $bulletParagraph = ['alignment' => $alignment, 'spaceAfter' => 80, 'indentation' => ['left' => 360, 'hanging' => 180]];

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $section->addTextBreak();
                continue;
            }

            if (preg_match('/^#\s+(.+)$/u', $trimmed, $matches) === 1) {
                $section->addTitle($this->stripInlineMarkdown($matches[1]), 1);
                continue;
            }

            if (preg_match('/^##\s+(.+)$/u', $trimmed, $matches) === 1) {
                $section->addTitle($this->stripInlineMarkdown($matches[1]), 2);
                continue;
            }

            if (preg_match('/^###\s+(.+)$/u', $trimmed, $matches) === 1) {
                $section->addTitle($this->stripInlineMarkdown($matches[1]), 3);
                continue;
            }

            if (preg_match('/^[-*]\s+\[( |x|X)\]\s+(.+)$/u', $trimmed, $matches) === 1) {
                $section->addText('[' . ($matches[1] === ' ' ? ' ' : 'x') . '] ' . $this->stripInlineMarkdown($matches[2]), [], $bulletParagraph);
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/u', $trimmed, $matches) === 1) {
                $section->addText('- ' . $this->stripInlineMarkdown($matches[1]), [], $bulletParagraph);
                continue;
            }

            if (preg_match('/^(\d+)[.)]\s+(.+)$/u', $trimmed, $matches) === 1) {
                $section->addText($matches[1] . '. ' . $this->stripInlineMarkdown($matches[2]), [], $bulletParagraph);
                continue;
            }

            if (preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?$/u', $trimmed) === 1) {
                continue;
            }

            if (str_contains($trimmed, '|') && ! preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?$/u', $trimmed)) {
                $section->addText($this->stripInlineMarkdown(trim($trimmed, '| ')), ['name' => 'Courier New', 'size' => 9], $paragraph);
                continue;
            }

            $section->addText($this->stripInlineMarkdown($trimmed), [], $paragraph);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);
    }

    private function normalizeDocumentBody(string $title, string $content): string
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return '# ' . $title;
        }

        if (preg_match('/^#{1,3}\s/u', $trimmed) === 1) {
            return $trimmed . "\n";
        }

        return '# ' . $title . "\n\n" . $trimmed . "\n";
    }

    private function contentWithDisclaimer(string $content, ?string $disclaimer): string
    {
        $content = trim($content);
        $disclaimer = trim((string) $disclaimer);

        if ($disclaimer === '' || str_contains($content, $disclaimer)) {
            return $content;
        }

        return $content . "\n\n---\n\n" . $disclaimer;
    }

    private function stripInlineMarkdown(string $text): string
    {
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/u', '$1 ($2)', $text) ?? $text;
        $text = str_replace(['**', '__', '`'], '', $text);

        return trim($text);
    }

    private function existsAndIsPreferred(array $download): bool
    {
        $storagePath = $download['storage_path'] ?? null;

        if (! is_string($storagePath) || ! Storage::disk('local')->exists($storagePath)) {
            return false;
        }

        $extension = Str::lower((string) ($download['extension'] ?? pathinfo($storagePath, PATHINFO_EXTENSION)));

        return $extension === 'docx';
    }

    private function containsArabic(string $text): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;
    }

    private function wordMimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
}
