<?php

namespace App\Jobs;

use App\Exceptions\KnowledgeProcessingStoppedException;
use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\AI\AiProviderManager;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\TextChunker;
use App\Services\Vector\Contracts\VectorStoreInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class ProcessKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(private int $documentId) {}

    public static function dispatchWithSyncFallback(int $documentId): void
    {
        $dispatcher = app(Dispatcher::class);
        $job = new self($documentId);

        try {
            $dispatcher->dispatch($job);
        } catch (Throwable) {
            $dispatcher->dispatchSync($job);
        }
    }

    public static function processNow(int $documentId): KnowledgeDocument
    {
        $job = new self($documentId);
        $lock = Cache::lock('knowledge-document-ingestion-sync', $job->timeout + 60);

        if (! $lock->get()) {
            return KnowledgeDocument::with('uploadedBy')->findOrFail($documentId);
        }

        try {
            try {
                $job->handle();
            } catch (Throwable $exception) {
                $job->failed($exception);
            }
        } finally {
            $lock->release();
        }

        return KnowledgeDocument::with('uploadedBy')->findOrFail($documentId);
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('knowledge-document-ingestion'))
                ->releaseAfter(5)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(): void
    {
        $document = KnowledgeDocument::findOrFail($this->documentId);
        $document->update([
            'status' => KnowledgeDocumentStatus::Processing->value,
            'processing_error' => null,
            'processing_started_at' => now(),
            'stop_requested_at' => null,
            'processed_at' => null,
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'total_chunks_count' => 0,
        ]);

        $collectionName = config('ai.qdrant_knowledge_collection');
        $vectorSize = config('ai.embedding_dimensions', 1536);

        $vectorStore = app(VectorStoreInterface::class);
        $vectorStore->ensureCollection($collectionName, $vectorSize);
        $vectorStore->deleteByFilter($collectionName, ['knowledge_document_id' => $document->id]);

        $extractor = app(DocumentTextExtractor::class);
        $pages = $extractor->extract($document->file_path, $document->disk);

        $chunker = app(TextChunker::class);
        $chunks = array_values(array_filter(
            $chunker->chunk($pages),
            fn (array $chunk): bool => ! empty(trim((string) ($chunk['content'] ?? '')))
        ));

        if ($chunks === []) {
            throw new RuntimeException('No readable text could be extracted from this document. Try a text-based PDF, DOCX, PPTX, or TXT file.');
        }

        $document->update([
            'total_chunks_count' => count($chunks),
        ]);

        $provider = AiProviderManager::resolve();
        $pointsCount = 0;

        try {
            foreach ($chunks as $chunk) {
                $this->abortIfStopRequested($collectionName);

                $embedding = $provider->embedding($chunk['content']);
                $pointId = "kb_{$document->id}_{$chunk['chunk_index']}";

                $vectorStore->upsertPoint($collectionName, $pointId, $embedding, [
                    'source_type' => 'knowledge_base',
                    'knowledge_document_id' => $document->id,
                    'document_name' => $document->original_name,
                    'title' => $document->title,
                    'category' => $document->category ?? 'general',
                    'page_number' => $chunk['page_number'],
                    'end_page_number' => $chunk['end_page_number'] ?? $chunk['page_number'],
                    'chunk_index' => $chunk['chunk_index'],
                    'content' => $chunk['content'],
                    'snippet' => $chunk['snippet'],
                    'word_count' => $chunk['word_count'] ?? null,
                    'status' => 'processed',
                ]);

                $pointsCount++;

                KnowledgeDocument::query()
                    ->whereKey($document->id)
                    ->update([
                        'qdrant_points_count' => $pointsCount,
                        'processed_chunks_count' => $pointsCount,
                    ]);
            }
        } catch (KnowledgeProcessingStoppedException) {
            $this->markAsCancelled($collectionName);

            return;
        }

        $document->refresh();
        $document->update([
            'status' => KnowledgeDocumentStatus::Processed->value,
            'qdrant_collection' => $collectionName,
            'qdrant_points_count' => $pointsCount,
            'processed_chunks_count' => $pointsCount,
            'stop_requested_at' => null,
            'processed_at' => now(),
            'processing_error' => null,
        ]);
    }

    private function abortIfStopRequested(string $collectionName): void
    {
        $document = KnowledgeDocument::withTrashed()->find($this->documentId);

        if (! $document) {
            throw new KnowledgeProcessingStoppedException('Knowledge document no longer exists.');
        }

        if ($document->trashed() || $document->stop_requested_at !== null) {
            try {
                app(VectorStoreInterface::class)->deleteByFilter(
                    $collectionName,
                    ['knowledge_document_id' => $this->documentId]
                );
            } catch (Throwable) {
                // Best effort cleanup before cancellation.
            }

            throw new KnowledgeProcessingStoppedException('Processing stopped by admin.');
        }
    }

    private function markAsCancelled(string $collectionName): void
    {
        $document = KnowledgeDocument::withTrashed()->find($this->documentId);

        if (! $document) {
            return;
        }

        if ($document->trashed()) {
            return;
        }

        $document->update([
            'status' => KnowledgeDocumentStatus::Cancelled->value,
            'qdrant_collection' => $collectionName,
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'processed_at' => null,
            'processing_error' => 'Processing stopped by admin.',
        ]);
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(VectorStoreInterface::class)->deleteByFilter(
                config('ai.qdrant_knowledge_collection'),
                ['knowledge_document_id' => $this->documentId]
            );
        } catch (Throwable) {
            // Ignore cleanup failures and preserve the original processing error.
        }

        $document = KnowledgeDocument::withTrashed()->find($this->documentId);

        if (! $document || $document->trashed()) {
            return;
        }

        $document->update([
            'status' => KnowledgeDocumentStatus::Failed->value,
            'processing_error' => $exception->getMessage(),
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'total_chunks_count' => 0,
            'stop_requested_at' => null,
            'processed_at' => null,
        ]);
    }
}
