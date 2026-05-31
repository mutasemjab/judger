<?php

namespace App\Services\Search;

use App\Models\Conversation;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Note;
use App\Models\Task;
use App\Models\Template;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    public function search(int $userId, string $query, int $limit = 5): array
    {
        $term = '%' . $query . '%';

        $cases = LegalCase::where('user_id', $userId)
            ->where(fn($q) => $q->where('title', 'LIKE', $term)->orWhere('case_number', 'LIKE', $term)->orWhere('client_name', 'LIKE', $term))
            ->limit($limit)
            ->get(['id', 'title', 'status', 'priority', 'created_at']);

        $tasks = Task::where('user_id', $userId)
            ->where(fn($q) => $q->where('title', 'LIKE', $term)->orWhere('description', 'LIKE', $term))
            ->limit($limit)
            ->get(['id', 'title', 'status', 'priority', 'due_date']);

        $notes = Note::where('user_id', $userId)
            ->where(fn($q) => $q->where('title', 'LIKE', $term)->orWhere('content', 'LIKE', $term))
            ->limit($limit)
            ->get(['id', 'title', 'type', 'created_at']);

        $hearings = Hearing::where('user_id', $userId)
            ->where(fn($q) => $q->where('title', 'LIKE', $term)->orWhere('location', 'LIKE', $term))
            ->limit($limit)
            ->get(['id', 'title', 'date', 'status']);

        $conversations = Conversation::where('user_id', $userId)
            ->where(fn($q) => $q->where('title', 'LIKE', $term)->orWhere('summary', 'LIKE', $term))
            ->limit($limit)
            ->get(['id', 'title', 'type', 'last_message_at']);

        $templates = Template::where('is_active', true)
            ->where(fn($q) => $q->where('title', 'LIKE', $term)->orWhere('description', 'LIKE', $term))
            ->limit($limit)
            ->get(['id', 'title', 'slug']);

        return [
            'cases' => $cases->values(),
            'tasks' => $tasks->values(),
            'notes' => $notes->values(),
            'hearings' => $hearings->values(),
            'conversations' => $conversations->values(),
            'templates' => $templates->values(),
            'total' => $cases->count() + $tasks->count() + $notes->count() + $hearings->count() + $conversations->count() + $templates->count(),
        ];
    }
}
