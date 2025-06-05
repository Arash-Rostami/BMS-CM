<?php

namespace App\Providers\Filament;

use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('dashboard')
            ->path('dashboard')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn() => __('resources/dashboard/strings.navigation_group.operational')),
                NavigationGroup::make()
                    ->label(fn() => __('resources/dashboard/strings.navigation_group.base')),
            ])
            ->login()
            ->spa()
            ->colors([
                'danger' => Color::Rose,
                'info' => Color::Blue,
                'primary' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
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
            ->favicon(Vite::asset('resources/img/logos/curves.png'))
            ->font(
                (app()->getLocale() == 'fa') ? 'IranYekan' : 'Roboto',
                url: Vite::asset('resources/css/layout/fonts.css'),
                provider: LocalFontProvider::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->maxContentWidth(MaxWidth::Full)
            ->spa()
            ->breadcrumbs()
            ->brandName('BMS')
            ->brandLogo(Vite::asset('resources/img/logos/curves.png'))
            ->brandLogoHeight('7rem')
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchDebounce('750ms')
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
