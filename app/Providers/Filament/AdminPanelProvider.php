<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Tables\Table;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->paginationPageOptions([5, 10, 25, 50, 100])
                ->defaultPaginationPageOption(50);
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->profile()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->passwordReset()
            ->emailVerification()
            ->brandLogo('/site-logo.png')
            ->brandName('Stonebound Staff')
            ->favicon('/favicon.ico')
            ->colors([
                'primary' => [
                    50 => 'oklch(0.95 0.09 188)',
                    100 => 'oklch(0.93 0.09 188)',
                    200 => 'oklch(0.88 0.09 188)',
                    300 => 'oklch(0.84 0.09 188)',
                    400 => 'oklch(0.8 0.09 188)',
                    500 => 'oklch(0.76 0.09 188)',
                    600 => 'oklch(0.72 0.09 188)',
                    700 => 'oklch(0.69 0.09 188)',
                    800 => 'oklch(0.65 0.09 188)',
                    900 => 'oklch(0.62 0.09 188)',
                    950 => 'oklch(0.59 0.09 188)',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->databaseNotifications()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
