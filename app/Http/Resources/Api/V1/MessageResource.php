<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role?->value,
            'content' => $this->content,
            'source_type' => $this->source_type?->value,
            'sources' => $this->sources,
            'disclaimer' => $this->disclaimer,
            'is_pinned' => $this->is_pinned,
            'created_at' => $this->created_at,
        ];
    }
}
