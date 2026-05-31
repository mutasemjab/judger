<?php

namespace App\Models;

use App\Enums\HearingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hearing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'legal_case_id', 'title', 'date', 'start_time',
        'end_time', 'location', 'notes', 'reminder_at', 'type', 'status',
    ];

    protected $casts = [
        'date' => 'date',
        'reminder_at' => 'datetime',
        'status' => HearingStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }
}
