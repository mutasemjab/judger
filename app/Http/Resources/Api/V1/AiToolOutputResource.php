<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AiToolOutputResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tool_type' => $this->tool_type?->value,
            'legal_case_id' => $this->legal_case_id,
            'case_document_id' => $this->case_document_id,
            'content' => $this->content,
            'disclaimer' => $this->disclaimer,
            'source_type' => $this->source_type,
            'sources' => $this->sources,
            'created_at' => $this->created_at,
        ];
    }
}
