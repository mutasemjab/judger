<?php

namespace App\Console\Commands;

use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class IngestKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:ingest {path=knowledge} {--category=} {--sync}';
    protected $description = 'Ingest knowledge documents from storage into the vector database';

    public function handle(): int
    {
        $storagePath = $this->argument('path');
        $category = $this->option('category');
        $sync = $this->option('sync');

        $files = Storage::files($storagePath);

        if (empty($files)) {
            $this->error("No files found in storage/{$storagePath}");
            return 1;
        }

        $this->info("Found " . count($files) . " files to ingest.");
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $mimeType = Storage::mimeType($filePath) ?: 'application/octet-stream';
            $fileSize = Storage::size($filePath);

            $document = KnowledgeDocument::firstOrCreate(
                ['file_path' => $filePath],
                [
                    'title' => $fileName,
                    'original_name' => $fileName,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'disk' => 'local',
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'category' => $category ?? 'general',
                ]
            );

            if ($sync) {
                ProcessKnowledgeDocumentJob::dispatchSync($document->id);
            } else {
                ProcessKnowledgeDocumentJob::dispatch($document->id);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Ingestion " . ($sync ? "completed" : "queued") . " for " . count($files) . " files.");

        return 0;
    }
}
