<?php

namespace App\Services\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class KnowledgeDocumentBackgroundLauncher
{
    public function start(KnowledgeDocument $document, bool $forceRestart = false): KnowledgeDocument
    {
        $previousStatus = $document->status?->value ?? KnowledgeDocumentStatus::Uploaded->value;

        $document->update([
            'status' => KnowledgeDocumentStatus::Processing->value,
            'processing_error' => __('messages.processing_preparing'),
            'processing_started_at' => now(),
            'stop_requested_at' => null,
            'processed_at' => null,
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'total_chunks_count' => 0,
        ]);

        try {
            $command = $this->buildBackgroundCommand($document->id, $forceRestart);
            $this->launchInBackground($command);

            Log::info('knowledge.processing.background_started', [
                'document_id' => $document->id,
                'restart' => $forceRestart,
            ]);

            return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
        } catch (Throwable $exception) {
            $document->update([
                'status' => $previousStatus,
                'processing_error' => __('messages.processing_launch_failed'),
                'processing_started_at' => null,
                'stop_requested_at' => null,
                'processed_at' => null,
                'qdrant_points_count' => 0,
                'processed_chunks_count' => 0,
                'total_chunks_count' => 0,
            ]);

            Log::error('knowledge.processing.background_failed', [
                'document_id' => $document->id,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new RuntimeException(__('messages.processing_launch_failed'));
        }
    }

    private function buildBackgroundCommand(int $documentId, bool $forceRestart): string
    {
        $phpBinary = $this->resolvePhpBinary();
        $artisanPath = base_path('artisan');

        if (! is_file($artisanPath)) {
            throw new RuntimeException('Could not find the Artisan console file.');
        }

        $parts = [
            escapeshellarg($phpBinary),
            escapeshellarg($artisanPath),
            'knowledge:process-document',
            (string) $documentId,
            '--no-interaction',
        ];

        if ($forceRestart) {
            $parts[] = '--restart';
        }

        return sprintf(
            'cd %s && nohup %s > /dev/null 2>&1 &',
            escapeshellarg(base_path()),
            implode(' ', $parts)
        );
    }

    private function launchInBackground(string $command): void
    {
        if (function_exists('proc_open')) {
            $process = Process::fromShellCommandline($command, base_path(), null, null, 10);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Background process could not be started.');
            }

            return;
        }

        if (function_exists('exec')) {
            $output = [];
            $exitCode = 0;
            @exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException('Background process could not be started.');
            }

            return;
        }

        throw new RuntimeException('This server does not allow starting a background process.');
    }

    private function resolvePhpBinary(): string
    {
        $finder = new PhpExecutableFinder();
        $phpBinary = $finder->find(false);

        if (is_string($phpBinary) && $phpBinary !== '') {
            return $phpBinary;
        }

        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            return PHP_BINARY;
        }

        return 'php';
    }
}
