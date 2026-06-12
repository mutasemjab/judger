<?php

namespace App\Services\Vector;

use App\Services\Vector\Contracts\VectorStoreInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseVectorStore implements VectorStoreInterface
{
    private const TABLE = 'vector_store_points';

    public function ensureCollection(string $collectionName, int $vectorSize): void
    {
        // Collections are logical only in the database fallback.
    }

    public function upsertPoint(string $collectionName, string|int $id, array $vector, array $payload): void
    {
        try {
            DB::table(self::TABLE)->upsert(
                [
                    array_merge(
                        $this->mappedColumns($payload),
                        [
                            'vector' => json_encode(array_values($vector), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                            'collection_name' => $collectionName,
                            'point_key' => (string) $id,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    ),
                ],
                ['collection_name', 'point_key'],
                [
                    'source_type',
                    'knowledge_document_id',
                    'case_document_id',
                    'legal_case_id',
                    'user_id',
                    'status',
                    'category',
                    'document_name',
                    'document_type',
                    'page_number',
                    'chunk_index',
                    'vector',
                    'payload',
                    'updated_at',
                ]
            );
        } catch (QueryException $exception) {
            throw $this->fallbackTableNotReady($exception);
        }
    }

    public function search(string $collectionName, array $vector, int $limit = 10, array $filter = []): array
    {
        try {
            $rows = $this->applyMappedFilters(
                DB::table(self::TABLE)->where('collection_name', $collectionName),
                $filter
            )->get();
        } catch (QueryException $exception) {
            throw $this->fallbackTableNotReady($exception);
        }

        $results = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true) ?: [];

            if (! $this->matchesPayloadFilters($payload, $filter)) {
                continue;
            }

            $storedVector = json_decode((string) $row->vector, true);

            if (! is_array($storedVector) || $storedVector === []) {
                continue;
            }

            $results[] = [
                'id' => $row->point_key,
                'score' => $this->cosineSimilarity($vector, $storedVector),
                'payload' => $payload,
            ];
        }

        usort($results, fn (array $left, array $right): int => ($right['score'] <=> $left['score']));

        return array_slice($results, 0, $limit);
    }

    public function deleteByFilter(string $collectionName, array $filter): void
    {
        try {
            $query = $this->applyMappedFilters(
                DB::table(self::TABLE)->where('collection_name', $collectionName),
                $filter
            );

            $rows = $query->get(['id', 'payload']);
            $ids = [];

            foreach ($rows as $row) {
                $payload = json_decode((string) $row->payload, true) ?: [];

                if ($this->matchesPayloadFilters($payload, $filter)) {
                    $ids[] = $row->id;
                }
            }

            if ($ids !== []) {
                DB::table(self::TABLE)->whereIn('id', $ids)->delete();
            }
        } catch (QueryException $exception) {
            throw $this->fallbackTableNotReady($exception);
        }
    }

    private function applyMappedFilters($query, array $filter)
    {
        foreach ($filter as $key => $value) {
            $column = $this->columnForFilter($key);

            if ($column !== null) {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    private function matchesPayloadFilters(array $payload, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            if (($payload[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    private function mappedColumns(array $payload): array
    {
        return [
            'source_type' => $payload['source_type'] ?? null,
            'knowledge_document_id' => $payload['knowledge_document_id'] ?? null,
            'case_document_id' => $payload['case_document_id'] ?? null,
            'legal_case_id' => $payload['legal_case_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'status' => $payload['status'] ?? null,
            'category' => $payload['category'] ?? null,
            'document_name' => $payload['document_name'] ?? null,
            'document_type' => $payload['document_type'] ?? null,
            'page_number' => $payload['page_number'] ?? null,
            'chunk_index' => $payload['chunk_index'] ?? null,
        ];
    }

    private function columnForFilter(string $key): ?string
    {
        return match ($key) {
            'source_type',
            'knowledge_document_id',
            'case_document_id',
            'legal_case_id',
            'user_id',
            'status',
            'category',
            'document_name',
            'document_type',
            'page_number',
            'chunk_index' => $key,
            default => null,
        };
    }

    private function cosineSimilarity(array $left, array $right): float
    {
        $length = min(count($left), count($right));
        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        for ($index = 0; $index < $length; $index++) {
            $leftValue = (float) ($left[$index] ?? 0.0);
            $rightValue = (float) ($right[$index] ?? 0.0);

            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue ** 2;
            $rightNorm += $rightValue ** 2;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }

    private function fallbackTableNotReady(QueryException $exception): RuntimeException
    {
        return new RuntimeException(
            'The local database index is not ready yet. Please run the latest migrations, including the vector_store_points table.',
            previous: $exception
        );
    }
}
