<?php

namespace App\Services\Vector;

use App\Services\Vector\Contracts\VectorStoreInterface;

class FakeVectorStore implements VectorStoreInterface
{
    private array $collections = [];
    private array $points = [];

    public function ensureCollection(string $collectionName, int $vectorSize): void
    {
        $this->collections[$collectionName] = ['size' => $vectorSize];
    }

    public function upsertPoint(string $collectionName, string|int $id, array $vector, array $payload): void
    {
        $this->points[$collectionName][$id] = [
            'id' => $id,
            'vector' => $vector,
            'payload' => $payload,
        ];
    }

    public function search(string $collectionName, array $vector, int $limit = 10, array $filter = []): array
    {
        $results = [];
        $points = $this->points[$collectionName] ?? [];

        foreach ($points as $point) {
            $matches = true;
            foreach ($filter as $key => $value) {
                if (($point['payload'][$key] ?? null) !== $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $results[] = [
                    'id' => $point['id'],
                    'score' => 0.85,
                    'payload' => $point['payload'],
                ];
            }
        }

        return array_slice($results, 0, $limit);
    }

    public function deleteByFilter(string $collectionName, array $filter): void
    {
        $points = $this->points[$collectionName] ?? [];
        foreach ($points as $id => $point) {
            $matches = true;
            foreach ($filter as $key => $value) {
                if (($point['payload'][$key] ?? null) !== $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                unset($this->points[$collectionName][$id]);
            }
        }
    }
}
