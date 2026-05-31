<?php

namespace App\Models;

use App\Enums\AiToolType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiToolOutput extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'legal_case_id', 'case_document_id', 'tool_type',
        'input', 'output', 'content', 'disclaimer', 'source_type', 'sources',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'sources' => 'array',
        'tool_type' => AiToolType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class, 'case_document_id');
    }
}
