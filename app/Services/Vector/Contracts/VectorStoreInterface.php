<?php

namespace App\Services\Vector\Contracts;

interface VectorStoreInterface
{
    public function ensureCollection(string $collectionName, int $vectorSize): void;

    public function upsertPoint(string $collectionName, string|int $id, array $vector, array $payload): void;

    public function search(string $collectionName, array $vector, int $limit = 10, array $filter = []): array;

    public function deleteByFilter(string $collectionName, array $filter): void;
}
