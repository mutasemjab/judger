<?php

namespace Database\Factories;

use App\Enums\PlanName;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => PlanName::Free->value,
            'display_name' => 'Free Plan',
            'price' => 0,
            'currency' => 'USD',
            'limits' => ['cases' => 3, 'ai_messages_daily' => 20],
            'features' => ['templates' => false, 'reminders' => false],
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state([
            'name' => PlanName::Free->value,
            'display_name' => 'Free',
            'price' => 0,
            'limits' => ['cases' => 3, 'ai_messages_daily' => 20, 'document_uploads_monthly' => 10],
            'features' => ['templates' => false, 'exports' => false],
        ]);
    }

    public function premium(): static
    {
        return $this->state([
            'name' => PlanName::Premium->value,
            'display_name' => 'Premium',
            'price' => 29.99,
            'billing_period' => 'monthly',
            'limits' => ['cases' => null, 'ai_messages_daily' => 200, 'document_uploads_monthly' => 100],
            'features' => ['templates' => true, 'exports' => true, 'reminders' => true],
        ]);
    }

    public function enterprise(): static
    {
        return $this->state([
            'name' => PlanName::Enterprise->value,
            'display_name' => 'Enterprise',
            'price' => 99.99,
            'billing_period' => 'monthly',
            'limits' => ['cases' => null, 'ai_messages_daily' => null],
            'features' => ['templates' => true, 'exports' => true, 'reminders' => true, 'teams' => true],
        ]);
    }
}
