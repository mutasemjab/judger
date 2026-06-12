<?php

namespace App\Console\Commands;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeDocumentStepProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessKnowledgeDocumentCommand extends Command
{
    protected $signature = 'knowledge:process-document {documentId} {--restart}';

    protected $description = 'Process one knowledge document in the background';

    public function handle(KnowledgeDocumentStepProcessor $processor): int
    {
        $documentId = (int) $this->argument('documentId');
        $forceRestart = (bool) $this->option('restart');

        $document = KnowledgeDocument::withTrashed()->find($documentId);

        if (! $document || $document->trashed()) {
            $this->error("Knowledge document {$documentId} was not found.");

            return self::FAILURE;
        }

        Log::info('knowledge.processing.background_runner_started', [
            'document_id' => $documentId,
            'restart' => $forceRestart,
        ]);

        $previousSignature = null;
        $idleLoops = 0;

        while (true) {
            try {
                $document = $processor->processNextStep($document, $forceRestart);
            } catch (Throwable $exception) {
                $currentDocument = KnowledgeDocument::withTrashed()->find($documentId);

                if (! $currentDocument || $currentDocument->trashed()) {
                    Log::info('knowledge.processing.background_runner_finished', [
                        'document_id' => $documentId,
                        'status' => 'deleted',
                    ]);

                    return self::SUCCESS;
                }

                throw $exception;
            }

            $forceRestart = false;
            $currentDocument = KnowledgeDocument::withTrashed()->find($documentId);

            if (! $currentDocument || $currentDocument->trashed()) {
                Log::info('knowledge.processing.background_runner_finished', [
                    'document_id' => $documentId,
                    'status' => 'deleted',
                ]);

                return self::SUCCESS;
            }

            $document = $currentDocument;
            $document->refresh();

            if ($document->status?->value !== KnowledgeDocumentStatus::Processing->value) {
                Log::info('knowledge.processing.background_runner_finished', [
                    'document_id' => $documentId,
                    'status' => $document->status?->value,
                    'processed_chunks' => $document->processed_chunks_count,
                    'total_chunks' => $document->total_chunks_count,
                ]);

                return $document->status === KnowledgeDocumentStatus::Processed
                    ? self::SUCCESS
                    : self::FAILURE;
            }

            $signature = implode(':', [
                (int) $document->processed_chunks_count,
                (int) $document->total_chunks_count,
                (string) optional($document->updated_at)->timestamp,
            ]);

            $idleLoops = $signature === $previousSignature ? $idleLoops + 1 : 0;
            $previousSignature = $signature;

            usleep($idleLoops >= 5 ? 500000 : 150000);
        }
    }
}
