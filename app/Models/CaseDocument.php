<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\OcrStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'legal_case_id', 'user_id', 'original_name', 'file_name', 'file_path',
        'disk', 'mime_type', 'file_size', 'document_type', 'status', 'ocr_status',
        'extracted_text', 'summary', 'insights', 'important_highlights',
        'detected_names', 'detected_dates', 'detected_case_numbers',
        'missing_document_suggestions', 'qdrant_collection', 'qdrant_points_count',
        'processing_error', 'processed_at',
    ];

    protected $hidden = ['file_path', 'extracted_text'];

    protected $casts = [
        'insights' => 'array',
        'important_highlights' => 'array',
        'detected_names' => 'array',
        'detected_dates' => 'array',
        'detected_case_numbers' => 'array',
        'missing_document_suggestions' => 'array',
        'processed_at' => 'datetime',
        'status' => DocumentStatus::class,
        'ocr_status' => OcrStatus::class,
    ];

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function insight(): HasOne
    {
        return $this->hasOne(DocumentInsight::class);
    }
}
