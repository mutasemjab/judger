<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'legal_case_id' => $this->legal_case_id,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type?->value,
            'is_pinned' => $this->is_pinned,
            'created_at' => $this->created_at,
        ];
    }
}
