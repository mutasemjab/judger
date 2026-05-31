<?php

namespace App\Models;

use App\Enums\MemoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseMemory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'legal_case_id', 'user_id', 'type', 'title', 'content',
        'confidence', 'source', 'source_message_id', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'confidence' => 'float',
        'type' => MemoryType::class,
    ];

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'source_message_id');
    }
}
