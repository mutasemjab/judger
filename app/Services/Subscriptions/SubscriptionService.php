<?php

namespace App\Services\Subscriptions;

use App\Enums\PlanName;
use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;

class SubscriptionService
{
    public function getUserPlan(User $user): ?SubscriptionPlan
    {
        $subscription = $user->subscription;
        if (!$subscription || !$subscription->isActive()) {
            return SubscriptionPlan::where('name', PlanName::Free->value)->first();
        }
        return $subscription->plan;
    }

    public function subscribeToPlan(User $user, int $planId): UserSubscription
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        $user->subscriptions()->where('status', SubscriptionStatus::Active->value)->update([
            'status' => SubscriptionStatus::Cancelled->value,
        ]);

        return UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active->value,
            'starts_at' => now(),
        ]);
    }

    public function cancel(User $user): bool
    {
        return (bool) $user->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->update(['status' => SubscriptionStatus::Cancelled->value]);
    }

    public function getActivePlans(): \Illuminate\Database\Eloquent\Collection
    {
        return SubscriptionPlan::where('is_active', true)->get();
    }
}
