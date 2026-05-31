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
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(private int $documentId) {}

    public function handle(): void
    {
        $document = KnowledgeDocument::findOrFail($this->documentId);
        $document->update(['status' => KnowledgeDocumentStatus::Processing->value]);

        $collectionName = config('ai.qdrant_knowledge_collection');
        $vectorSize = config('ai.embedding_dimensions', 1536);

        $vectorStore = new QdrantVectorStore();
        $vectorStore->ensureCollection($collectionName, $vectorSize);
        $vectorStore->deleteByFilter($collectionName, ['knowledge_document_id' => $document->id]);

        $extractor = new DocumentTextExtractor();
        $pages = $extractor->extract($document->file_path, $document->disk);

        $chunker = new TextChunker();
        $chunks = $chunker->chunk($pages);

        $provider = AiProviderManager::resolve();
        $pointsCount = 0;

        foreach ($chunks as $chunk) {
            if (empty(trim($chunk['content']))) {
                continue;
            }

            $embedding = $provider->embedding($chunk['content']);
            $pointId = "kb_{$document->id}_{$chunk['chunk_index']}";

            $vectorStore->upsertPoint($collectionName, $pointId, $embedding, [
                'source_type' => 'knowledge_base',
                'knowledge_document_id' => $document->id,
                'document_name' => $document->original_name,
                'title' => $document->title,
                'category' => $document->category ?? 'general',
                'page_number' => $chunk['page_number'],
                'chunk_index' => $chunk['chunk_index'],
                'content' => $chunk['content'],
                'snippet' => $chunk['snippet'],
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
        KnowledgeDocument::where('id', $this->documentId)->update([
            'status' => KnowledgeDocumentStatus::Failed->value,
            'processing_error' => $exception->getMessage(),
        ]);
    }
}
