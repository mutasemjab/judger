<?php

namespace App\Models;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'team_id', 'title', 'category', 'case_number', 'court',
        'court_name', 'jurisdiction', 'client_name', 'opposing_party',
        'description', 'status', 'priority', 'next_hearing_at', 'start_date',
        'end_date', 'tags', 'notes', 'summary', 'ai_summary',
    ];

    protected $casts = [
        'tags' => 'array',
        'next_hearing_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => CaseStatus::class,
        'priority' => CasePriority::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(CaseMemory::class);
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(Hearing::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CaseTag::class, 'case_case_tag', 'case_id', 'case_tag_id');
    }

    public function sharedCases(): HasMany
    {
        return $this->hasMany(SharedCase::class);
    }
}
