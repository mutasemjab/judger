<?php

namespace App\Models;

use App\Enums\SharedCasePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_case_id', 'team_id', 'shared_with_user_id',
        'permission_level', 'created_by',
    ];

    protected $casts = [
        'permission_level' => SharedCasePermission::class,
    ];

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
