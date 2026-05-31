<?php

namespace App\Repositories;

use App\Models\LegalCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LegalCaseRepository
{
    public function forUser(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = LegalCase::where('user_id', $userId)->with(['documents']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', $term)
                    ->orWhere('case_number', 'LIKE', $term)
                    ->orWhere('client_name', 'LIKE', $term)
                    ->orWhere('description', 'LIKE', $term);
            });
        }

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        return $query->paginate($perPage);
    }

    public function findForUser(int $caseId, int $userId): ?LegalCase
    {
        return LegalCase::where('id', $caseId)->where('user_id', $userId)->first();
    }

    public function create(int $userId, array $data): LegalCase
    {
        return LegalCase::create(array_merge($data, ['user_id' => $userId]));
    }
}
