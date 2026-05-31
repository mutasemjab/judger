<?php

namespace App\Models;

use App\Enums\KnowledgeDocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'original_name', 'file_name', 'file_path', 'disk',
        'mime_type', 'file_size', 'category', 'status', 'uploaded_by',
        'qdrant_collection', 'qdrant_points_count', 'processing_error', 'processed_at',
    ];

    protected $hidden = ['file_path'];

    protected $casts = [
        'processed_at' => 'datetime',
        'status' => KnowledgeDocumentStatus::class,
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
