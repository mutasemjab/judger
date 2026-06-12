<?php

namespace App\Providers;

use App\Services\AI\AiProviderManager;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\Subscriptions\FeatureGateService;
use App\Services\Subscriptions\SubscriptionService;
use App\Services\Subscriptions\UsageService;
use App\Services\Vector\Contracts\VectorStoreInterface;
use App\Services\Vector\VectorStoreManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmProviderInterface::class, function () {
            return AiProviderManager::resolve();
        });

        $this->app->scoped(VectorStoreManager::class);

        $this->app->scoped(VectorStoreInterface::class, function () {
            return app(VectorStoreManager::class)->resolve();
        });

        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(UsageService::class);
        $this->app->singleton(FeatureGateService::class, function ($app) {
            return new FeatureGateService(
                $app->make(SubscriptionService::class),
                $app->make(UsageService::class)
            );
        });
    }

    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrap();
    }
}
