<?php

namespace App\Models;

use App\Enums\UsagePeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'key', 'period', 'count', 'limit_value', 'reset_at', 'metadata',
    ];

    protected $casts = [
        'count' => 'integer',
        'limit_value' => 'integer',
        'reset_at' => 'datetime',
        'metadata' => 'array',
        'period' => UsagePeriod::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExceeded(): bool
    {
        if ($this->limit_value === null) {
            return false;
        }
        return $this->count >= $this->limit_value;
    }
}
