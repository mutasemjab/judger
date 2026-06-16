<?php

namespace App\Services\Documents;

use App\Models\AiToolOutput;
use App\Models\GeneratedDocument;
use App\Models\Message;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratedFileExportService
{
    public function exportMessage(Message $message): array
    {
        $existing = $message->metadata['download'] ?? null;
        if (is_array($existing) && $this->exists($existing)) {
            return $existing;
        }

        $download = $this->storeMarkdown(
            directory: "generated/chat_messages/user_{$message->conversation->user_id}",
            title: $message->conversation->title ?: 'judger-ai-response',
            content: $message->content,
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
        if (is_array($existing) && $this->exists($existing)) {
            return $existing;
        }

        $download = $this->storeMarkdown(
            directory: "generated/ai_tools/user_{$output->user_id}",
            title: $output->tool_type?->label() ?? 'judger-ai-output',
            content: $output->content ?? '',
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

        if (is_string($existingPath) && Storage::disk('local')->exists($existingPath)) {
            return $this->hydrateDownload(
                storagePath: $existingPath,
                fileName: basename($existingPath),
                url: "/api/v1/generated-documents/{$document->id}/download"
            );
        }

        $download = $this->storeMarkdown(
            directory: "generated/template_documents/user_{$document->user_id}",
            title: $document->title ?: 'generated-document',
            content: $document->content,
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
            ['Content-Type' => $download['mime_type'] ?? 'text/markdown']
        );
    }

    private function storeMarkdown(string $directory, string $title, string $content, string $url): array
    {
        $slug = Str::slug(Str::limit($title, 60, ''));
        $slug = $slug !== '' ? $slug : 'judger-export';
        $fileName = $slug . '-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(6)) . '.md';
        $storagePath = trim($directory, '/') . '/' . $fileName;

        Storage::disk('local')->put($storagePath, $this->normalizeDocumentBody($title, $content));

        return $this->hydrateDownload($storagePath, $fileName, $url);
    }

    private function hydrateDownload(string $storagePath, string $fileName, string $url): array
    {
        return [
            'storage_path' => $storagePath,
            'file_name' => $fileName,
            'mime_type' => 'text/markdown',
            'available' => true,
            'url' => $url,
        ];
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

    private function exists(array $download): bool
    {
        $storagePath = $download['storage_path'] ?? null;

        return is_string($storagePath) && Storage::disk('local')->exists($storagePath);
    }
}
