<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'category' => $this->category,
            'status' => $this->status?->value,
            'qdrant_points_count' => $this->qdrant_points_count,
            'processed_chunks_count' => $this->processed_chunks_count,
            'total_chunks_count' => $this->total_chunks_count,
            'processing_error' => $this->processing_error,
            'processing_started_at' => $this->processing_started_at,
            'stop_requested_at' => $this->stop_requested_at,
            'processed_at' => $this->processed_at,
            'can_start_processing' => $this->canStartProcessing(),
            'can_stop_processing' => $this->canStopProcessing(),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn() => [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
