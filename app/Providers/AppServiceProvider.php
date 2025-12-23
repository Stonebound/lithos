<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\OverrideRule;
use App\Models\Release;
use App\Models\Server;
use App\Models\User;
use App\Observers\OverrideRuleObserver;
use App\Observers\ReleaseObserver;
use App\Observers\ServerObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Server::observe(ServerObserver::class);
        Release::observe(ReleaseObserver::class);
        User::observe(UserObserver::class);
        OverrideRule::observe(OverrideRuleObserver::class);
    }
}
