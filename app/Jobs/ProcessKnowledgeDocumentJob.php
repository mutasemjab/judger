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
use Illuminate\Support\Facades\Log;
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
        ignore_user_abort(true);
        @set_time_limit(0);

        $document = KnowledgeDocument::findOrFail($this->documentId);
        Log::info('knowledge.processing.started', [
            'document_id' => $document->id,
            'title' => $document->title,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
        ]);

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
        Log::info('knowledge.processing.extracted', [
            'document_id' => $document->id,
            'pages' => count($pages),
            'non_empty_pages' => count(array_filter($pages, fn (array $page): bool => trim((string) ($page['text'] ?? '')) !== '')),
        ]);

        $chunker = app(TextChunker::class);
        $chunks = array_values(array_filter(
            $chunker->chunk($pages),
            fn (array $chunk): bool => ! empty(trim((string) ($chunk['content'] ?? '')))
        ));
        Log::info('knowledge.processing.chunked', [
            'document_id' => $document->id,
            'chunks' => count($chunks),
            'first_chunk_words' => $chunks[0]['word_count'] ?? 0,
            'last_chunk_words' => $chunks !== [] ? ($chunks[array_key_last($chunks)]['word_count'] ?? 0) : 0,
        ]);

        if ($chunks === []) {
            throw new RuntimeException('No readable text could be extracted from this document. If this is a scanned PDF, enable OCR support with Arabic or English Tesseract language data on the server, or upload a text-based PDF, DOCX, PPTX, or TXT file.');
        }

        $document->update([
            'total_chunks_count' => count($chunks),
        ]);

        $provider = AiProviderManager::resolveEmbedding();
        $pointsCount = 0;
        $batchSize = max(1, (int) config('ai.embedding_batch_size', 12));
        $chunkBatches = array_chunk($chunks, $batchSize);

        try {
            foreach ($chunkBatches as $batchIndex => $chunkBatch) {
                $this->abortIfStopRequested($collectionName);
                Log::info('knowledge.processing.embedding_batch_started', [
                    'document_id' => $document->id,
                    'batch' => $batchIndex + 1,
                    'total_batches' => count($chunkBatches),
                    'batch_size' => count($chunkBatch),
                ]);

                $embeddings = $provider->embeddingMany(array_column($chunkBatch, 'content'));

                if (count($embeddings) !== count($chunkBatch)) {
                    throw new RuntimeException('OpenAI returned an unexpected number of embeddings for this batch.');
                }

                foreach ($chunkBatch as $offset => $chunk) {
                    $embedding = $embeddings[$offset] ?? [];

                    if ($embedding === []) {
                        throw new RuntimeException('An embedding batch item was empty.');
                    }

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
                }

                KnowledgeDocument::query()
                    ->whereKey($document->id)
                    ->update([
                        'qdrant_points_count' => $pointsCount,
                        'processed_chunks_count' => $pointsCount,
                    ]);

                Log::info('knowledge.processing.embedding_batch_completed', [
                    'document_id' => $document->id,
                    'batch' => $batchIndex + 1,
                    'total_batches' => count($chunkBatches),
                    'processed_chunks' => $pointsCount,
                    'total_chunks' => count($chunks),
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
        Log::info('knowledge.processing.completed', [
            'document_id' => $document->id,
            'chunks_indexed' => $pointsCount,
            'collection' => $collectionName,
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
        Log::warning('knowledge.processing.cancelled', [
            'document_id' => $document->id,
            'collection' => $collectionName,
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
        Log::error('knowledge.processing.failed', [
            'document_id' => $document->id,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
