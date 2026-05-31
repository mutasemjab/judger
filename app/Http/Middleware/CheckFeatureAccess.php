<?php

namespace App\Http\Middleware;

use App\Services\Subscriptions\FeatureGateService;
use App\Services\Subscriptions\SubscriptionService;
use App\Services\Subscriptions\UsageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $gateService = new FeatureGateService(new SubscriptionService(), new UsageService());
        $result = $gateService->canAccess($user, $feature);

        if (!$result['allowed']) {
            return response()->json([
                'success' => false,
                'message' => 'This feature is available on ' . ucfirst($result['required_plan'] ?? 'Premium') . '.',
                'data' => [
                    'feature' => $result['feature'] ?? $feature,
                    'required_plan' => $result['required_plan'] ?? 'premium',
                    'upgrade_required' => true,
                ],
            ], 403);
        }

        return $next($request);
    }
}
