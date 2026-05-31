<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NoteType;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Message;
use App\Models\Note;
use Illuminate\Http\JsonResponse;

class MessageController extends BaseApiController
{
    public function pin(Message $message): JsonResponse
    {
        $this->authorize('pin', $message);
        $message->update(['is_pinned' => !$message->is_pinned]);
        return $this->success(new MessageResource($message->fresh()), $message->is_pinned ? 'Message pinned.' : 'Message unpinned.');
    }

    public function saveAsNote(Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        $note = Note::create([
            'user_id' => auth('api')->id(),
            'legal_case_id' => $message->legal_case_id,
            'title' => 'From AI Chat - ' . now()->format('Y-m-d H:i'),
            'content' => $message->content,
            'type' => NoteType::AiGenerated->value,
        ]);

        return $this->created(['note_id' => $note->id], 'Message saved as note.');
    }
}
