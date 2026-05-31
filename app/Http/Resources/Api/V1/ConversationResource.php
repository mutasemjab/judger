<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'title' => $this->title,
            'legal_case_id' => $this->legal_case_id,
            'summary' => $this->summary,
            'last_message_at' => $this->last_message_at,
            'messages_count' => $this->whenCounted('messages'),
            'created_at' => $this->created_at,
        ];
    }
}
