<?php

namespace App\Providers;

use App\Events\AiResponseGenerated;
use App\Events\CaseCreated;
use App\Events\DocumentAnalyzed;
use App\Events\DocumentUploaded;
use App\Listeners\LogActivity;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        CaseCreated::class => [
            LogActivity::class,
        ],
        DocumentUploaded::class => [
            LogActivity::class,
        ],
        DocumentAnalyzed::class => [
            LogActivity::class,
        ],
        AiResponseGenerated::class => [
            LogActivity::class,
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
