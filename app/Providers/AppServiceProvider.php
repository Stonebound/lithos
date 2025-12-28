<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\OverrideRule;
use App\Models\Release;
use App\Models\Server;
use App\Models\SrvRecord;
use App\Models\User;
use App\Models\WhitelistUser;
use App\Observers\OverrideRuleObserver;
use App\Observers\ReleaseObserver;
use App\Observers\ServerObserver;
use App\Observers\SrvRecordObserver;
use App\Observers\UserObserver;
use App\Observers\WhitelistUserObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
        WhitelistUser::observe(WhitelistUserObserver::class);
        OverrideRule::observe(OverrideRuleObserver::class);
        SrvRecord::observe(SrvRecordObserver::class);

        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;
            if ($user instanceof User) {
                $user->last_logged_in_at = now();
                $user->save();
            }
        });
    }
}
