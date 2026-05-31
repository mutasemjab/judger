<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_document_id', 'legal_case_id', 'user_id', 'summary', 'insights',
        'highlights', 'detected_entities', 'detected_dates', 'detected_risks',
        'missing_documents', 'disclaimer',
    ];

    protected $casts = [
        'insights' => 'array',
        'highlights' => 'array',
        'detected_entities' => 'array',
        'detected_dates' => 'array',
        'detected_risks' => 'array',
        'missing_documents' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class, 'case_document_id');
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
