<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $metadata = $this->metadata ?? [];

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role?->value,
            'content' => $this->content,
            'source_type' => $this->source_type?->value,
            'sources' => $this->sources,
            'disclaimer' => $this->disclaimer,
            'is_pinned' => $this->is_pinned,
            'follow_up_questions' => $metadata['follow_up_questions'] ?? [],
            'next_question_prompt' => $metadata['next_question_prompt'] ?? null,
            'presentation' => $metadata['presentation'] ?? null,
            'scope' => $metadata['scope'] ?? null,
            'download' => isset($metadata['download']) ? $this->publicDownload($metadata['download']) : null,
            'metadata' => $this->publicMetadata($metadata),
            'attachments' => ChatAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
        ];
    }

    private function publicDownload(array $download): array
    {
        unset($download['storage_path']);

        return $download;
    }

    private function publicMetadata(array $metadata): array
    {
        if (isset($metadata['download']) && is_array($metadata['download'])) {
            $metadata['download'] = $this->publicDownload($metadata['download']);
        }

        return $metadata;
    }
}
