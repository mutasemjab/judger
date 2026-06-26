<?php

namespace App\Services\Search;

use App\Services\AI\AiProviderManager;
use App\Services\Vector\Contracts\VectorStoreInterface;

class CaseDocumentSearchService
{
    public function search(int $userId, int $legalCaseId, string $question, ?int $limit = null): array
    {
        $provider = AiProviderManager::resolveEmbedding();
        $embedding = $provider->embedding($question);

        return $this->searchByEmbedding($userId, $legalCaseId, $embedding, $limit);
    }

    public function searchByEmbedding(int $userId, int $legalCaseId, array $embedding, ?int $limit = null): array
    {
        if ($embedding === []) {
            return [];
        }

        $limit = $limit ?? config('ai.max_case_document_chunks', 8);
        $threshold = config('ai.document_similarity_threshold', 0.70);

        $vectorStore = app(VectorStoreInterface::class);
        $collectionName = config('ai.qdrant_case_collection');

        $results = $vectorStore->search($collectionName, $embedding, $limit, [
            'user_id' => $userId,
            'legal_case_id' => $legalCaseId,
            'status' => 'processed',
        ]);

        return array_filter($results, fn($r) => ($r['score'] ?? 0) >= $threshold);
    }
}
