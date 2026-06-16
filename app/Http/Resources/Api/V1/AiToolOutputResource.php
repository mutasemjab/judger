<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AiToolOutputResource extends JsonResource
{
    public function toArray($request): array
    {
        $output = $this->output ?? [];

        return [
            'id' => $this->id,
            'tool_type' => $this->tool_type?->value,
            'legal_case_id' => $this->legal_case_id,
            'case_document_id' => $this->case_document_id,
            'content' => $this->content,
            'disclaimer' => $this->disclaimer,
            'source_type' => $this->source_type,
            'sources' => $this->sources,
            'follow_up_questions' => $output['follow_up_questions'] ?? [],
            'next_question_prompt' => $output['next_question_prompt'] ?? null,
            'presentation' => $output['presentation'] ?? null,
            'scope' => $output['scope'] ?? null,
            'download' => isset($output['download']) ? $this->publicDownload($output['download']) : null,
            'created_at' => $this->created_at,
        ];
    }

    private function publicDownload(array $download): array
    {
        unset($download['storage_path']);

        return $download;
    }
}
