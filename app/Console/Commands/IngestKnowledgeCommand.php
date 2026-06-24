<?php

namespace App\Console\Commands;

use App\Enums\KnowledgeDocumentStatus;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeDocumentStepProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

class IngestKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:ingest
        {path=knowledge : Storage path, absolute file path, or local directory path}
        {--category=general : Category assigned to imported documents}
        {--disk=local : Storage disk used when the path is not a real filesystem path}
        {--process : Generate embeddings immediately in this CLI process}
        {--sync : Alias for --process, kept for older scripts}
        {--queue : Queue processing jobs instead of processing inline}
        {--register-only : Only create/update knowledge document rows}
        {--force : Re-import existing files and restart their embeddings}
        {--dry-run : Preview the files that would be imported}
        {--limit= : Maximum number of supported files to import}';

    protected $description = 'Import local/storage knowledge files and optionally embed them into the AI knowledge base';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $disk = (string) $this->option('disk');
        $category = trim((string) $this->option('category')) ?: 'general';
        $shouldProcess = (bool) ($this->option('process') || $this->option('sync'));
        $shouldQueue = (bool) $this->option('queue');
        $registerOnly = (bool) $this->option('register-only');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $limit = $this->resolveLimit();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($shouldProcess && $shouldQueue) {
            $this->error('Use either --process/--sync or --queue, not both.');

            return self::FAILURE;
        }

        if ($registerOnly && ($shouldProcess || $shouldQueue)) {
            $this->error('Use --register-only without --process, --sync, or --queue.');

            return self::FAILURE;
        }

        try {
            [$files, $skipped] = $this->discoverFiles($path, $disk, $limit);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($files === []) {
            $this->error('No supported knowledge files were found.');

            if ($skipped !== []) {
                $this->warn('Skipped files: '.count($skipped));
            }

            return self::FAILURE;
        }

        $this->info('Found '.count($files).' supported file(s).');
        $this->line('Supported extensions: '.implode(', ', KnowledgeDocument::SUPPORTED_EXTENSIONS));

        if ($skipped !== []) {
            $this->warn('Skipped '.count($skipped).' unsupported/unreadable file(s).');
        }

        if ($dryRun) {
            $this->renderDryRun($files, $skipped);

            return self::SUCCESS;
        }

        $processor = app(KnowledgeDocumentStepProcessor::class);
        $imported = 0;
        $queued = 0;
        $processed = 0;
        $failed = 0;
        $existing = 0;

        foreach ($files as $index => $file) {
            $this->line(sprintf(
                '[%d/%d] %s',
                $index + 1,
                count($files),
                $file['display_path']
            ));

            try {
                [$document, $wasExisting] = $this->importFile($file, $category, $force);
                $imported++;

                if ($wasExisting) {
                    $existing++;
                }

                $this->line(sprintf(
                    '  Document #%d: %s',
                    $document->id,
                    $wasExisting ? 'already registered' : 'registered'
                ));

                if ($registerOnly) {
                    continue;
                }

                if ($shouldProcess) {
                    $document = $this->processDocument($processor, $document, $force);

                    if ($document->status === KnowledgeDocumentStatus::Processed) {
                        $processed++;
                    } else {
                        $failed++;
                    }

                    continue;
                }

                if ($shouldQueue) {
                    ProcessKnowledgeDocumentJob::dispatch($document->id);
                    $queued++;
                }
            } catch (Throwable $exception) {
                $failed++;

                Log::error('knowledge.ingest.file_failed', [
                    'source' => $file['display_path'] ?? null,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                $this->error('  Failed: '.$exception->getMessage());
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Import finished: %d registered, %d existing, %d queued, %d processed, %d failed.',
            $imported,
            $existing,
            $queued,
            $processed,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveLimit(): ?int
    {
        $value = $this->option('limit');

        if ($value === null || $value === '') {
            return null;
        }

        $limit = (int) $value;

        if ($limit < 1) {
            throw new RuntimeException('--limit must be a positive integer.');
        }

        return $limit;
    }

    private function discoverFiles(string $path, string $disk, ?int $limit): array
    {
        $realPath = realpath($path);

        if ($realPath !== false) {
            return $this->filterFiles($this->scanFilesystemPath($realPath), $limit);
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new RuntimeException("Path not found as a local path or storage path: {$path}");
        }

        $absolutePath = $storage->path($path);

        if (is_file($absolutePath)) {
            return $this->filterFiles([[
                'source' => 'storage',
                'disk' => $disk,
                'path' => $path,
                'absolute_path' => $absolutePath,
                'display_path' => "{$disk}:{$path}",
                'original_name' => basename($path),
            ]], $limit);
        }

        $files = [];

        foreach ($storage->allFiles($path) as $filePath) {
            $files[] = [
                'source' => 'storage',
                'disk' => $disk,
                'path' => $filePath,
                'absolute_path' => $storage->path($filePath),
                'display_path' => "{$disk}:{$filePath}",
                'original_name' => basename($filePath),
            ];
        }

        return $this->filterFiles($files, $limit);
    }

    private function scanFilesystemPath(string $path): array
    {
        if (is_file($path)) {
            return [[
                'source' => 'filesystem',
                'path' => $path,
                'absolute_path' => $path,
                'display_path' => $path,
                'original_name' => basename($path),
            ]];
        }

        if (! is_dir($path)) {
            throw new RuntimeException("Path is not a readable file or directory: {$path}");
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $absolutePath = $file->getPathname();

            $files[] = [
                'source' => 'filesystem',
                'path' => $absolutePath,
                'absolute_path' => $absolutePath,
                'display_path' => $absolutePath,
                'original_name' => $file->getBasename(),
            ];
        }

        usort($files, fn (array $left, array $right): int => strcmp($left['display_path'], $right['display_path']));

        return $files;
    }

    private function filterFiles(array $files, ?int $limit): array
    {
        $supportedExtensions = array_flip(array_map('strtolower', KnowledgeDocument::SUPPORTED_EXTENSIONS));
        $accepted = [];
        $skipped = [];

        foreach ($files as $file) {
            $extension = strtolower(pathinfo((string) $file['original_name'], PATHINFO_EXTENSION));

            if ($extension === '' || ! isset($supportedExtensions[$extension]) || ! is_readable($file['absolute_path'])) {
                $skipped[] = $file;

                continue;
            }

            $file['extension'] = $extension;
            $file['mime_type'] = mime_content_type($file['absolute_path']) ?: 'application/octet-stream';
            $file['file_size'] = filesize($file['absolute_path']) ?: 0;
            $accepted[] = $file;

            if ($limit !== null && count($accepted) >= $limit) {
                break;
            }
        }

        return [$accepted, $skipped];
    }

    private function renderDryRun(array $files, array $skipped): void
    {
        $rows = [];

        foreach (array_slice($files, 0, 25) as $file) {
            $rows[] = [
                $file['extension'],
                $this->formatBytes((int) $file['file_size']),
                $file['display_path'],
            ];
        }

        $this->table(['Type', 'Size', 'Path'], $rows);

        if (count($files) > 25) {
            $this->line('...and '.(count($files) - 25).' more supported file(s).');
        }

        if ($skipped !== []) {
            $this->line('First skipped file: '.$skipped[0]['display_path']);
        }
    }

    private function importFile(array $file, string $category, bool $force): array
    {
        $targetDisk = $file['source'] === 'storage' ? $file['disk'] : 'local';
        $targetPath = $file['source'] === 'storage'
            ? $file['path']
            : $this->copyFilesystemFileIntoStorage($file, $force);

        $document = KnowledgeDocument::withTrashed()
            ->where('disk', $targetDisk)
            ->where('file_path', $targetPath)
            ->first();

        $wasExisting = $document !== null && ! $document->trashed();

        if ($document === null) {
            $document = new KnowledgeDocument([
                'disk' => $targetDisk,
                'file_path' => $targetPath,
            ]);
        }

        if ($document->exists && $document->trashed()) {
            $document->restore();
        }

        if ($document->exists && $force) {
            app(KnowledgeDocumentStepProcessor::class)->cleanupDocumentState($document);
        }

        if (! $document->exists || $force) {
            $document->forceFill([
                'title' => KnowledgeDocument::normalizeTitle(null, $file['original_name']),
                'original_name' => $file['original_name'],
                'file_name' => basename($targetPath),
                'file_path' => $targetPath,
                'disk' => $targetDisk,
                'mime_type' => $file['mime_type'],
                'file_size' => $file['file_size'],
                'category' => $category,
                'status' => KnowledgeDocumentStatus::Uploaded->value,
                'qdrant_collection' => null,
                'qdrant_points_count' => 0,
                'processed_chunks_count' => 0,
                'total_chunks_count' => 0,
                'processing_error' => null,
                'processing_started_at' => null,
                'stop_requested_at' => null,
                'processed_at' => null,
            ])->save();
        }

        return [$document->fresh(), $wasExisting];
    }

    private function copyFilesystemFileIntoStorage(array $file, bool $force): string
    {
        $originalName = (string) $file['original_name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $slug = (string) Str::of(pathinfo($originalName, PATHINFO_FILENAME))
            ->ascii()
            ->slug('-')
            ->limit(80, '');

        if ($slug === '') {
            $slug = 'document';
        }

        $hash = sha1((string) $file['absolute_path']);
        $targetPath = "knowledge_documents/imported/{$hash}-{$slug}.{$extension}";

        if (! $force && Storage::disk('local')->exists($targetPath)) {
            return $targetPath;
        }

        $stream = fopen((string) $file['absolute_path'], 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read source file.');
        }

        try {
            if (! Storage::disk('local')->put($targetPath, $stream)) {
                throw new RuntimeException('Unable to copy source file into storage.');
            }
        } finally {
            fclose($stream);
        }

        return $targetPath;
    }

    private function processDocument(
        KnowledgeDocumentStepProcessor $processor,
        KnowledgeDocument $document,
        bool $forceRestart
    ): KnowledgeDocument {
        $document->refresh();

        if (! $forceRestart && $document->status === KnowledgeDocumentStatus::Processed) {
            $this->line('  Already processed.');

            return $document;
        }

        $this->line('  Generating embeddings...');

        $restart = $forceRestart;
        $progressBar = null;
        $lastProgress = null;

        while (true) {
            $document = $processor->processNextStep($document, $restart);
            $restart = false;
            $document->refresh();

            $total = (int) $document->total_chunks_count;
            $processed = (int) $document->processed_chunks_count;
            $signature = "{$processed}/{$total}";

            if ($total > 0) {
                if ($progressBar === null) {
                    $progressBar = $this->output->createProgressBar($total);
                    $progressBar->start();
                }

                if ($signature !== $lastProgress) {
                    $progressBar->setProgress(min($processed, $total));
                    $lastProgress = $signature;
                }
            }

            if ($document->status !== KnowledgeDocumentStatus::Processing) {
                if ($progressBar !== null) {
                    $progressBar->finish();
                    $this->newLine();
                }

                $this->line(sprintf(
                    '  Finished with status %s (%d/%d chunks).',
                    $document->status?->value ?? 'unknown',
                    $processed,
                    $total
                ));

                return $document;
            }

            usleep(150000);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
