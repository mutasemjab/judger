<?php

namespace App\Services\Subscriptions;

use App\Models\User;

class FeatureGateService
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private UsageService $usageService
    ) {}

    public function canAccess(User $user, string $feature): array
    {
        $plan = $this->subscriptionService->getUserPlan($user);
        if (!$plan) {
            return $this->locked($feature, 'premium');
        }

        $planName = $plan->name->value;
        $planConfig = config("features.plans.{$planName}", []);

        $boolFeatures = ['templates', 'reminders', 'exports', 'teams', 'knowledge_base_management', 'advanced_ai'];

        if (in_array($feature, $boolFeatures)) {
            $allowed = $planConfig['features'][$feature] ?? false;
            if (!$allowed) {
                $requiredPlan = $this->getRequiredPlan($feature);
                return $this->locked($feature, $requiredPlan);
            }
            return $this->allowed();
        }

        $limitKey = $this->getLimitKey($feature);
        if (!$limitKey) {
            return $this->allowed();
        }

        $limit = $planConfig[$limitKey] ?? null;
        if ($limit === null) {
            return $this->allowed();
        }

        $usage = $this->usageService->getCount($user, $feature);
        if ($usage >= $limit) {
            $requiredPlan = $this->getRequiredPlan($feature);
            return $this->locked($feature, $requiredPlan);
        }

        return $this->allowed();
    }

    private function allowed(): array
    {
        return ['allowed' => true];
    }

    private function locked(string $feature, string $requiredPlan): array
    {
        return [
            'allowed' => false,
            'feature' => $feature,
            'required_plan' => $requiredPlan,
            'upgrade_required' => true,
        ];
    }

    private function getLimitKey(string $feature): ?string
    {
        return match ($feature) {
            'cases_created' => 'cases',
            'ai_messages' => 'ai_messages_daily',
            'document_uploads' => 'document_uploads_monthly',
            'document_analysis' => 'document_analysis_monthly',
            'template_generations' => 'template_generations_monthly',
            default => null,
        };
    }

    private function getRequiredPlan(string $feature): string
    {
        return match ($feature) {
            'teams', 'knowledge_base_management' => 'enterprise',
            default => 'premium',
        };
    }
}
