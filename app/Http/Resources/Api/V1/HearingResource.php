<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class HearingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'legal_case_id' => $this->legal_case_id,
            'title' => $this->title,
            'date' => $this->date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'location' => $this->location,
            'notes' => $this->notes,
            'reminder_at' => $this->reminder_at,
            'type' => $this->type,
            'status' => $this->status?->value,
            'created_at' => $this->created_at,
        ];
    }
}
