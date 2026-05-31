<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Services\Subscriptions\FeatureGateService;
use App\Services\Subscriptions\SubscriptionService;
use App\Services\Subscriptions\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private UsageService $usageService,
        private FeatureGateService $featureGate
    ) {}

    public function plans(): JsonResponse
    {
        $plans = $this->subscriptionService->getActivePlans();
        return $this->success(SubscriptionPlanResource::collection($plans));
    }

    public function current(): JsonResponse
    {
        $user = auth('api')->user();
        $plan = $this->subscriptionService->getUserPlan($user);

        return $this->success([
            'plan' => $plan ? new SubscriptionPlanResource($plan) : null,
            'subscription' => $user->subscription ? [
                'id' => $user->subscription->id,
                'status' => $user->subscription->status?->value,
                'starts_at' => $user->subscription->starts_at,
                'ends_at' => $user->subscription->ends_at,
            ] : null,
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate(['plan_id' => 'required|integer|exists:subscription_plans,id']);
        $subscription = $this->subscriptionService->subscribeToPlan(auth('api')->user(), $request->plan_id);
        return $this->success(['subscription_id' => $subscription->id], 'Subscribed successfully.');
    }

    public function cancel(): JsonResponse
    {
        $this->subscriptionService->cancel(auth('api')->user());
        return $this->success(null, 'Subscription cancelled.');
    }

    public function usage(): JsonResponse
    {
        $usage = $this->usageService->getAllUsage(auth('api')->user());
        return $this->success($usage);
    }

    public function features(): JsonResponse
    {
        $user = auth('api')->user();
        $plan = $this->subscriptionService->getUserPlan($user);
        $planName = $plan?->name?->value ?? 'free';
        $planConfig = config("features.plans.{$planName}", []);

        return $this->success([
            'plan' => $planName,
            'limits' => $planConfig,
            'features' => $planConfig['features'] ?? [],
        ]);
    }
}
