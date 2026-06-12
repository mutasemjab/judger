<?php

namespace App\Jobs;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\AI\AiProviderManager;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\TextChunker;
use App\Services\Vector\QdrantVectorStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class ProcessKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(private int $documentId) {}

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
            'processed_at' => null,
            'qdrant_points_count' => 0,
        ]);

        $collectionName = config('ai.qdrant_knowledge_collection');
        $vectorSize = config('ai.embedding_dimensions', 1536);

        $vectorStore = new QdrantVectorStore();
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

        $provider = AiProviderManager::resolve();
        $pointsCount = 0;

        foreach ($chunks as $chunk) {
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
        }

        $document->update([
            'status' => KnowledgeDocumentStatus::Processed->value,
            'qdrant_collection' => $collectionName,
            'qdrant_points_count' => $pointsCount,
            'processed_at' => now(),
            'processing_error' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        try {
            (new QdrantVectorStore())->deleteByFilter(
                config('ai.qdrant_knowledge_collection'),
                ['knowledge_document_id' => $this->documentId]
            );
        } catch (Throwable) {
            // Ignore cleanup failures and preserve the original processing error.
        }

        KnowledgeDocument::where('id', $this->documentId)->update([
            'status' => KnowledgeDocumentStatus::Failed->value,
            'processing_error' => $exception->getMessage(),
            'qdrant_points_count' => 0,
            'processed_at' => null,
        ]);
    }
}
