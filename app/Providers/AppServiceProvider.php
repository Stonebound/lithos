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
use App\Services\PhpUploadLimit;
use App\Utils\ForeverProcessFactory;
use Illuminate\Auth\Events\Login;
use Illuminate\Concurrency\ConcurrencyManager;
use Illuminate\Concurrency\ProcessDriver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->afterResolving(ConcurrencyManager::class, function (ConcurrencyManager $manager): void {
            $manager->extend('forever-process', fn (Application $app): ProcessDriver => new ProcessDriver(
                $app->make(ForeverProcessFactory::class),
            ));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config()->set('livewire.temporary_file_upload.rules', [
            'required',
            'file',
            'max:'.PhpUploadLimit::maxUploadKilobytes(),
        ]);

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
