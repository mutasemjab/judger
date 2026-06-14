<?php

namespace App\Services\Search;

use App\Services\AI\AiProviderManager;
use App\Services\Vector\Contracts\VectorStoreInterface;

class KnowledgeSearchService
{
    public function search(string $question, ?int $limit = null): array
    {
        $limit = $limit ?? config('ai.max_knowledge_chunks', 8);
        $threshold = config('ai.document_similarity_threshold', 0.70);

        $provider = AiProviderManager::resolveEmbedding();
        $embedding = $provider->embedding($question);

        $vectorStore = app(VectorStoreInterface::class);
        $collectionName = config('ai.qdrant_knowledge_collection');

        $results = $vectorStore->search($collectionName, $embedding, $limit, [
            'status' => 'processed',
        ]);

        return array_filter($results, fn($r) => ($r['score'] ?? 0) >= $threshold);
    }
}
