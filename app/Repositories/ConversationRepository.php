<?php

namespace App\Repositories;

use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConversationRepository
{
    public function forUser(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Conversation::where('user_id', $userId)->orderByDesc('last_message_at');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate($perPage);
    }

    public function findForUser(int $conversationId, int $userId): ?Conversation
    {
        return Conversation::where('id', $conversationId)->where('user_id', $userId)->first();
    }
}
