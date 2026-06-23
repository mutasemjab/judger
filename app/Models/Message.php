<?php

namespace App\Models;

use App\Enums\MessageRole;
use App\Enums\MessageSourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'user_id', 'legal_case_id', 'role', 'content',
        'source_type', 'sources', 'metadata', 'disclaimer', 'is_pinned',
        'parent_message_id', 'regenerated_from_id',
    ];

    protected $casts = [
        'sources' => 'array',
        'metadata' => 'array',
        'is_pinned' => 'boolean',
        'role' => MessageRole::class,
        'source_type' => MessageSourceType::class,
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class);
    }
}
