<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CaseDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'legal_case_id' => $this->legal_case_id,
            'original_name' => $this->original_name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'document_type' => $this->document_type,
            'status' => $this->status?->value,
            'ocr_status' => $this->ocr_status?->value,
            'summary' => $this->summary,
            'insights' => $this->insights,
            'important_highlights' => $this->important_highlights,
            'detected_names' => $this->detected_names,
            'detected_dates' => $this->detected_dates,
            'detected_case_numbers' => $this->detected_case_numbers,
            'missing_document_suggestions' => $this->missing_document_suggestions,
            'qdrant_points_count' => $this->qdrant_points_count,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
