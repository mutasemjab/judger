<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'free',
                'display_name' => 'Free',
                'description' => 'Get started with basic legal tools.',
                'price' => 0,
                'currency' => 'USD',
                'billing_period' => null,
                'limits' => [
                    'cases' => 3,
                    'ai_messages_daily' => 20,
                    'document_uploads_monthly' => 10,
                    'document_analysis_monthly' => 5,
                    'storage_mb' => 100,
                    'template_generations_monthly' => 5,
                ],
                'features' => [
                    'templates' => false,
                    'reminders' => false,
                    'exports' => false,
                    'teams' => false,
                    'knowledge_base_management' => false,
                    'advanced_ai' => false,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'premium',
                'display_name' => 'Premium',
                'description' => 'Unlimited cases, advanced AI, and more.',
                'price' => 29.99,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'limits' => [
                    'cases' => null,
                    'ai_messages_daily' => 200,
                    'document_uploads_monthly' => 100,
                    'document_analysis_monthly' => 50,
                    'storage_mb' => 2048,
                    'template_generations_monthly' => 100,
                ],
                'features' => [
                    'templates' => true,
                    'reminders' => true,
                    'exports' => true,
                    'teams' => false,
                    'knowledge_base_management' => false,
                    'advanced_ai' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise',
                'description' => 'Team collaboration, analytics, and full access.',
                'price' => 99.99,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'limits' => [
                    'cases' => null,
                    'ai_messages_daily' => null,
                    'document_uploads_monthly' => null,
                    'document_analysis_monthly' => null,
                    'storage_mb' => null,
                    'template_generations_monthly' => null,
                ],
                'features' => [
                    'templates' => true,
                    'reminders' => true,
                    'exports' => true,
                    'teams' => true,
                    'knowledge_base_management' => true,
                    'advanced_ai' => true,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(['name' => $plan['name']], $plan);
        }

        $freePlan = SubscriptionPlan::where('name', 'free')->first();
        if ($freePlan) {
            User::whereDoesntHave('subscriptions')->each(function (User $user) use ($freePlan) {
                UserSubscription::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'subscription_plan_id' => $freePlan->id,
                        'status' => 'active',
                        'starts_at' => now(),
                    ]
                );
            });
        }
    }
}
