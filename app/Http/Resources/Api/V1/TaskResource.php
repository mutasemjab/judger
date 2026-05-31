<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'legal_case_id' => $this->legal_case_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'due_date' => $this->due_date,
            'completed_at' => $this->completed_at,
            'ai_suggested_next_action' => $this->ai_suggested_next_action,
            'created_at' => $this->created_at,
        ];
    }
}
