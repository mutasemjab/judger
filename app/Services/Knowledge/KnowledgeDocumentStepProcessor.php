<?php

namespace App\Services\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\AI\AiProviderManager;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\TextChunker;
use App\Services\Vector\Contracts\VectorStoreInterface;
use App\Services\Vector\VectorStoreManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class KnowledgeDocumentStepProcessor
{
    public function processNextStep(KnowledgeDocument $document, bool $forceRestart = false): KnowledgeDocument
    {
        ignore_user_abort(true);
        @set_time_limit(0);

        $stepStartedAt = microtime(true);
        $stepCompleted = false;
        $phase = 'booting';
        $batchOffset = null;
        $batchSize = null;

        register_shutdown_function(function () use (
            $document,
            $forceRestart,
            $stepStartedAt,
            &$stepCompleted,
            &$phase,
            &$batchOffset,
            &$batchSize
        ): void {
            if ($stepCompleted) {
                return;
            }

            $error = error_get_last();

            Log::critical('knowledge.processing.step_terminated', [
                'document_id' => $document->id,
                'force_restart' => $forceRestart,
                'phase' => $phase,
                'batch_offset' => $batchOffset,
                'batch_size' => $batchSize,
                'duration_ms' => (int) round((microtime(true) - $stepStartedAt) * 1000),
                'connection_aborted' => connection_aborted(),
                'memory_peak_bytes' => memory_get_peak_usage(true),
                'fatal_error' => $error,
            ]);
        });

        $lock = Cache::lock($this->lockKey($document->id), 300);

        if (! $lock->get()) {
            $stepCompleted = true;

            return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
        }

        try {
            $document->refresh();

            if ($forceRestart) {
                $this->resetDocument($document);
            }

            $state = $this->loadState($document->id);

            if ($document->stop_requested_at !== null) {
                return $this->markAsCancelled($document, $this->collectionName($document, $state));
            }

            if ($state === null) {
                $phase = 'prepare_processing_state';

                return $this->prepareProcessingState($document);
            }

            if ($document->status !== KnowledgeDocumentStatus::Processing) {
                $document->update([
                    'status' => KnowledgeDocumentStatus::Processing->value,
                    'processing_error' => null,
                    'processing_started_at' => $document->processing_started_at ?: now(),
                    'processed_at' => null,
                ]);
                $document->refresh();
            }

            $chunks = $state['chunks'] ?? [];

            if (! is_array($chunks) || $chunks === []) {
                throw new RuntimeException('Knowledge document processing state is empty. Please restart processing.');
            }

            $totalChunks = count($chunks);
            $processedCount = min((int) $document->processed_chunks_count, $totalChunks);

            if ((int) $document->total_chunks_count !== $totalChunks) {
                $document->update(['total_chunks_count' => $totalChunks]);
            }

            if ($processedCount >= $totalChunks) {
                return $this->markAsProcessed($document, $this->collectionName($document, $state), $processedCount);
            }

            $stepSize = max(1, (int) config('ai.knowledge_processing_step_chunk_count', 4));
            $chunkBatch = array_slice($chunks, $processedCount, $stepSize);
            $batchOffset = $processedCount;
            $batchSize = count($chunkBatch);
            $phase = 'resolve_embedding_provider';

            $provider = AiProviderManager::resolveEmbedding();
            $vectorStoreManager = app(VectorStoreManager::class);
            $collectionName = $this->collectionName($document, $state);

            Log::info('knowledge.processing.embedding_batch_started', [
                'document_id' => $document->id,
                'batch_offset' => $processedCount,
                'batch_size' => count($chunkBatch),
                'total_chunks' => $totalChunks,
                'provider_config' => config('ai.provider', 'mock'),
                'embedding_provider_config' => config('ai.embedding_provider', config('ai.provider', 'mock')),
                'resolved_provider' => $provider::class,
                'embedding_model' => config('ai.embedding_model'),
                'embedding_dimensions' => (int) config('ai.embedding_dimensions', 1536),
                'embedding_connect_timeout' => (int) config('ai.embedding_connect_timeout', 15),
                'embedding_request_timeout' => (int) config('ai.embedding_request_timeout', 45),
                'vector_store_config' => config('ai.vector_store', 'auto'),
                'vector_store_driver' => $vectorStoreManager->driver(),
                'collection' => $collectionName,
            ]);

            $phase = 'embedding_request';
            $embeddingStartedAt = microtime(true);
            $inputs = array_column($chunkBatch, 'content');

            try {
                $embeddings = $provider->embeddingMany($inputs);
            } catch (Throwable $exception) {
                Log::error('knowledge.processing.embedding_request_failed', [
                    'document_id' => $document->id,
                    'batch_offset' => $processedCount,
                    'batch_size' => count($chunkBatch),
                    'duration_ms' => (int) round((microtime(true) - $embeddingStartedAt) * 1000),
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            Log::info('knowledge.processing.embedding_request_completed', [
                'document_id' => $document->id,
                'batch_offset' => $processedCount,
                'batch_size' => count($chunkBatch),
                'duration_ms' => (int) round((microtime(true) - $embeddingStartedAt) * 1000),
                'embeddings_returned' => count($embeddings),
            ]);

            if (count($embeddings) !== count($chunkBatch)) {
                throw new RuntimeException('OpenAI returned an unexpected number of embeddings for this batch.');
            }

            $vectorStore = app(VectorStoreInterface::class);
            $phase = 'vector_write';
            $vectorStartedAt = microtime(true);

            Log::info('knowledge.processing.vector_write_started', [
                'document_id' => $document->id,
                'batch_offset' => $processedCount,
                'batch_size' => count($chunkBatch),
                'vector_store_driver' => $vectorStoreManager->driver(),
                'collection' => $collectionName,
                'first_chunk_index' => $chunkBatch[0]['chunk_index'] ?? null,
                'last_chunk_index' => $chunkBatch !== [] ? ($chunkBatch[array_key_last($chunkBatch)]['chunk_index'] ?? null) : null,
            ]);

            try {
                foreach ($chunkBatch as $offset => $chunk) {
                    $embedding = $embeddings[$offset] ?? [];

                    if (! is_array($embedding) || $embedding === []) {
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
                }
            } catch (Throwable $exception) {
                Log::error('knowledge.processing.vector_write_failed', [
                    'document_id' => $document->id,
                    'batch_offset' => $processedCount,
                    'batch_size' => count($chunkBatch),
                    'duration_ms' => (int) round((microtime(true) - $vectorStartedAt) * 1000),
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'collection' => $collectionName,
                    'vector_store_driver' => $vectorStoreManager->driver(),
                ]);

                throw $exception;
            }

            Log::info('knowledge.processing.vector_write_completed', [
                'document_id' => $document->id,
                'batch_offset' => $processedCount,
                'batch_size' => count($chunkBatch),
                'duration_ms' => (int) round((microtime(true) - $vectorStartedAt) * 1000),
                'collection' => $collectionName,
                'vector_store_driver' => $vectorStoreManager->driver(),
            ]);

            $processedCount += count($chunkBatch);

            $document->update([
                'status' => KnowledgeDocumentStatus::Processing->value,
                'processing_error' => null,
                'qdrant_collection' => $collectionName,
                'qdrant_points_count' => $processedCount,
                'processed_chunks_count' => $processedCount,
                'total_chunks_count' => $totalChunks,
            ]);

            Log::info('knowledge.processing.embedding_batch_completed', [
                'document_id' => $document->id,
                'processed_chunks' => $processedCount,
                'total_chunks' => $totalChunks,
            ]);

            $document->refresh();

            if ($document->stop_requested_at !== null) {
                return $this->markAsCancelled($document, $collectionName);
            }

            if ($processedCount >= $totalChunks) {
                return $this->markAsProcessed($document, $collectionName, $processedCount);
            }

            return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
        } catch (Throwable $exception) {
            $phase = 'mark_failed';
            $this->markAsFailed($document->id, $exception);

            return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
        } finally {
            $stepCompleted = true;
            $lock->release();
        }
    }

    public function requestStop(KnowledgeDocument $document): KnowledgeDocument
    {
        $document->update([
            'stop_requested_at' => now(),
            'processing_error' => __('messages.stop_requested_message'),
        ]);

        $lock = Cache::lock($this->lockKey($document->id), 1);

        if ($lock->get()) {
            try {
                $document->refresh();

                if ($document->status === KnowledgeDocumentStatus::Processing) {
                    return $this->markAsCancelled($document, $this->collectionName($document, $this->loadState($document->id)));
                }
            } finally {
                $lock->release();
            }
        }

        return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
    }

    public function cleanupDocumentState(KnowledgeDocument $document): void
    {
        $this->deleteState($document->id);

        try {
            app(VectorStoreInterface::class)->deleteByFilter(
                $this->collectionName($document, null),
                ['knowledge_document_id' => $document->id]
            );
        } catch (Throwable) {
            // Best effort cleanup.
        }
    }

    private function prepareProcessingState(KnowledgeDocument $document): KnowledgeDocument
    {
        $collectionName = config('ai.qdrant_knowledge_collection');
        $vectorSize = config('ai.embedding_dimensions', 1536);

        $document->update([
            'status' => KnowledgeDocumentStatus::Processing->value,
            'processing_error' => null,
            'processing_started_at' => now(),
            'stop_requested_at' => null,
            'processed_at' => null,
            'qdrant_collection' => $collectionName,
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'total_chunks_count' => 0,
        ]);

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

        $this->saveState($document->id, [
            'collection' => $collectionName,
            'chunks' => $chunks,
        ]);

        $document->update([
            'total_chunks_count' => count($chunks),
        ]);

        Log::info('knowledge.processing.step_prepared', [
            'document_id' => $document->id,
            'total_chunks' => count($chunks),
        ]);

        return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
    }

    private function markAsProcessed(KnowledgeDocument $document, string $collectionName, int $processedCount): KnowledgeDocument
    {
        $this->deleteState($document->id);

        $document->update([
            'status' => KnowledgeDocumentStatus::Processed->value,
            'qdrant_collection' => $collectionName,
            'qdrant_points_count' => $processedCount,
            'processed_chunks_count' => $processedCount,
            'stop_requested_at' => null,
            'processed_at' => now(),
            'processing_error' => null,
        ]);

        Log::info('knowledge.processing.completed', [
            'document_id' => $document->id,
            'chunks_indexed' => $processedCount,
            'collection' => $collectionName,
        ]);

        return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
    }

    private function markAsCancelled(KnowledgeDocument $document, string $collectionName): KnowledgeDocument
    {
        $this->deleteState($document->id);

        try {
            app(VectorStoreInterface::class)->deleteByFilter(
                $collectionName,
                ['knowledge_document_id' => $document->id]
            );
        } catch (Throwable) {
            // Ignore cleanup failures while cancelling.
        }

        $document->update([
            'status' => KnowledgeDocumentStatus::Cancelled->value,
            'qdrant_collection' => $collectionName,
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'processed_at' => null,
            'processing_error' => __('messages.stop_requested_message'),
        ]);

        Log::warning('knowledge.processing.cancelled', [
            'document_id' => $document->id,
            'collection' => $collectionName,
        ]);

        return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
    }

    private function markAsFailed(int $documentId, Throwable $exception): void
    {
        $document = KnowledgeDocument::find($documentId);

        if (! $document) {
            return;
        }

        $this->deleteState($documentId);

        try {
            app(VectorStoreInterface::class)->deleteByFilter(
                $this->collectionName($document, null),
                ['knowledge_document_id' => $documentId]
            );
        } catch (Throwable) {
            // Best effort cleanup before marking the document as failed.
        }

        $document->update([
            'status' => KnowledgeDocumentStatus::Failed->value,
            'processing_error' => $exception->getMessage(),
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'stop_requested_at' => null,
            'processed_at' => null,
        ]);

        Log::error('knowledge.processing.failed', [
            'document_id' => $documentId,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }

    private function resetDocument(KnowledgeDocument $document): void
    {
        $this->deleteState($document->id);

        try {
            app(VectorStoreInterface::class)->deleteByFilter(
                $this->collectionName($document, null),
                ['knowledge_document_id' => $document->id]
            );
        } catch (Throwable) {
            // Ignore cleanup failures during reset.
        }

        $document->update([
            'status' => KnowledgeDocumentStatus::Uploaded->value,
            'processing_error' => null,
            'processing_started_at' => null,
            'stop_requested_at' => null,
            'processed_at' => null,
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'total_chunks_count' => 0,
        ]);

        $document->refresh();
    }

    private function statePath(int $documentId): string
    {
        return "knowledge_processing/document_{$documentId}.json";
    }

    private function saveState(int $documentId, array $state): void
    {
        Storage::disk('local')->put(
            $this->statePath($documentId),
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
        );
    }

    private function loadState(int $documentId): ?array
    {
        if (! Storage::disk('local')->exists($this->statePath($documentId))) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk('local')->get($this->statePath($documentId)), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function deleteState(int $documentId): void
    {
        Storage::disk('local')->delete($this->statePath($documentId));
    }

    private function collectionName(KnowledgeDocument $document, ?array $state): string
    {
        return (string) ($state['collection'] ?? $document->qdrant_collection ?: config('ai.qdrant_knowledge_collection'));
    }

    private function lockKey(int $documentId): string
    {
        return "knowledge-document-step-{$documentId}";
    }
}
