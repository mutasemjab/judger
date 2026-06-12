<?php

namespace App\Services\Vector;

use App\Services\Vector\Contracts\VectorStoreInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class QdrantVectorStore implements VectorStoreInterface
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('ai.qdrant_url', 'http://localhost:6333'), '/');
        $this->apiKey = config('ai.qdrant_api_key');
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $http = Http::timeout(30)->baseUrl($this->baseUrl);
        if ($this->apiKey) {
            $http = $http->withHeaders(['api-key' => $this->apiKey]);
        }
        return $http;
    }

    public function ensureCollection(string $collectionName, int $vectorSize): void
    {
        $response = $this->request()->get("/collections/{$collectionName}");

        if ($response->status() === 404) {
            $response = $this->request()->put("/collections/{$collectionName}", [
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => 'Cosine',
                ],
            ]);

            if ($response->failed()) {
                throw new RuntimeException("Failed to create Qdrant collection {$collectionName}: " . $response->body());
            }
        }
    }

    public function upsertPoint(string $collectionName, string|int $id, array $vector, array $payload): void
    {
        $response = $this->request()->put("/collections/{$collectionName}/points", [
            'points' => [
                [
                    'id' => is_string($id) ? crc32($id) : $id,
                    'vector' => $vector,
                    'payload' => $payload,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Failed to upsert Qdrant point: " . $response->body());
        }
    }

    public function search(string $collectionName, array $vector, int $limit = 10, array $filter = []): array
    {
        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ];

        if (!empty($filter)) {
            $body['filter'] = $this->buildFilter($filter);
        }

        $response = $this->request()->post("/collections/{$collectionName}/points/search", $body);

        if ($response->failed()) {
            return [];
        }

        return $response->json('result', []);
    }

    public function deleteByFilter(string $collectionName, array $filter): void
    {
        $response = $this->request()->post("/collections/{$collectionName}/points/delete", [
            'filter' => $this->buildFilter($filter),
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Failed to delete Qdrant points: " . $response->body());
        }
    }

    private function buildFilter(array $conditions): array
    {
        $must = [];
        foreach ($conditions as $key => $value) {
            $must[] = [
                'key' => $key,
                'match' => ['value' => $value],
            ];
        }
        return ['must' => $must];
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(2)
                ->baseUrl($this->baseUrl)
                ->when($this->apiKey, fn ($http) => $http->withHeaders(['api-key' => $this->apiKey]))
                ->get('/collections');

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
