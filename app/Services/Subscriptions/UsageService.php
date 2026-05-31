<?php

namespace App\Services\Subscriptions;

use App\Enums\UsagePeriod;
use App\Models\UsageCounter;
use App\Models\User;
use Carbon\Carbon;

class UsageService
{
    public function increment(User $user, string $key, int $amount = 1): UsageCounter
    {
        $period = $this->getPeriodForKey($key);
        $resetAt = $this->getResetAt($period);

        $counter = UsageCounter::firstOrCreate(
            ['user_id' => $user->id, 'key' => $key, 'period' => $period->value],
            ['count' => 0, 'reset_at' => $resetAt]
        );

        if ($counter->reset_at && now()->isAfter($counter->reset_at)) {
            $counter->update(['count' => $amount, 'reset_at' => $this->getResetAt($period)]);
        } else {
            $counter->increment('count', $amount);
            $counter->refresh();
        }

        return $counter;
    }

    public function getCount(User $user, string $key): int
    {
        $period = $this->getPeriodForKey($key);

        $counter = UsageCounter::where('user_id', $user->id)
            ->where('key', $key)
            ->where('period', $period->value)
            ->first();

        if (!$counter) {
            return 0;
        }

        if ($counter->reset_at && now()->isAfter($counter->reset_at)) {
            return 0;
        }

        return $counter->count;
    }

    public function getAllUsage(User $user): array
    {
        $keys = ['cases_created', 'ai_messages', 'document_uploads', 'document_analysis', 'storage_used_mb', 'template_generations'];
        $usage = [];
        foreach ($keys as $key) {
            $usage[$key] = $this->getCount($user, $key);
        }
        return $usage;
    }

    private function getPeriodForKey(string $key): UsagePeriod
    {
        return match ($key) {
            'cases_created' => UsagePeriod::Lifetime,
            'ai_messages' => UsagePeriod::Daily,
            'document_uploads', 'document_analysis', 'template_generations' => UsagePeriod::Monthly,
            default => UsagePeriod::Monthly,
        };
    }

    private function getResetAt(UsagePeriod $period): ?Carbon
    {
        return match ($period) {
            UsagePeriod::Daily => now()->endOfDay(),
            UsagePeriod::Monthly => now()->endOfMonth(),
            UsagePeriod::Yearly => now()->endOfYear(),
            UsagePeriod::Lifetime => null,
        };
    }
}
