<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class LegalCaseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'case_number' => $this->case_number,
            'court' => $this->court,
            'court_name' => $this->court_name,
            'jurisdiction' => $this->jurisdiction,
            'client_name' => $this->client_name,
            'opposing_party' => $this->opposing_party,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'next_hearing_at' => $this->next_hearing_at,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'tags' => $this->tags,
            'ai_summary' => $this->ai_summary,
            'documents_count' => $this->whenCounted('documents'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
