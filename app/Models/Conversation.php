<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'legal_case_id', 'type', 'title', 'summary', 'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'type' => ConversationType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class);
    }

    public function isGeneral(): bool
    {
        return $this->type === ConversationType::General;
    }

    public function isCase(): bool
    {
        return $this->type === ConversationType::Case;
    }
}
