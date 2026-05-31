<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogRepository
{
    public function log(
        ?int $userId,
        string $action,
        ?string $description = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
        ?Request $request = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function forAdmin(array $filters = [], int $perPage = 20)
    {
        return ActivityLog::with('user')
            ->when(!empty($filters['user_id']), fn($q) => $q->where('user_id', $filters['user_id']))
            ->when(!empty($filters['action']), fn($q) => $q->where('action', $filters['action']))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
