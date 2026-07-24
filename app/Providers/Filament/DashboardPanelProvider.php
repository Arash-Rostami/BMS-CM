<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\CustomLogin;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AccountWidget;
use App\Filament\Widgets\ConcentrationRiskWidget;
use App\Filament\Widgets\ExposureAgingWidget;
use App\Filament\Widgets\OpenCurrencyExposureWidget;
use App\Filament\Widgets\PipelineStallWidget;
use App\Filament\Widgets\ShipmentPunctualityWidget;
use App\Filament\Widgets\TradeCycleTimeWidget;
use App\Http\Middleware\EnsureUserIsActive;
use Filament\Actions\Action;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Platform;
use Filament\Support\Enums\Width;
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
            ->id('dashboard')
            ->path('dashboard')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn () => __('resources/dashboard/strings.navigation_group.operational_first'))
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(fn () => __('resources/dashboard/strings.navigation_group.operational_second'))
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(fn () => __('resources/dashboard/strings.navigation_group.operational_third'))
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(fn () => __('resources/dashboard/strings.navigation_group.operational_fourth'))
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(fn () => __('resources/dashboard/strings.navigation_group.base'))
                    ->collapsed(),
            ])
            ->login(CustomLogin::class)
            ->userMenu()
            ->userMenuItems([
                Action::make('reset_cache')
                    ->label(__('resources/general/strings.reset_cache.label'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() || (bool) auth()->user()?->hasRole([
                        UserRole::MANAGER_JUNIOR->value,
                        UserRole::MANAGER_MID->value,
                    ]))
                    ->action(function (): void {
                        dispatch(fn () => resetApplicationCache())->afterResponse();

                        Notification::make()
                            ->title(__('resources/general/strings.reset_cache.success'))
                            ->success()
                            ->send();
                    }),
            ])
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
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                ConcentrationRiskWidget::class,
                PipelineStallWidget::class,
                ShipmentPunctualityWidget::class,
                TradeCycleTimeWidget::class,
                ExposureAgingWidget::class,
                OpenCurrencyExposureWidget::class,
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
            ->favicon(Vite::asset('resources/'.config('app.branding.favicon')))
            ->font(
                (app()->getLocale() == 'fa') ? 'IranYekan' : 'Roboto',
                url: Vite::asset('resources/css/layout/fonts.css'),
                provider: LocalFontProvider::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->maxContentWidth(Width::Full)
            ->spa()
            ->globalSearch(true, position: GlobalSearchPosition::Topbar)
            ->globalSearchFieldSuffix(fn (): ?string => match (Platform::detect()) {
                Platform::Windows, Platform::Linux => 'Ctrl+K',
                Platform::Mac => '⌘K',
                default => null,
            })
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchDebounce('1000ms')
            ->breadcrumbs()
            ->brandName(config('app.name'))
            ->brandLogo(Vite::asset('resources/'.config('app.branding.logo.light')))
            ->darkModeBrandLogo(Vite::asset('resources/'.config('app.branding.logo.dark')))
            ->brandLogoHeight('3rem')
            ->sidebarWidth('15.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->default()
            ->authMiddleware([Authenticate::class, EnsureUserIsActive::class])
            ->defaultThemeMode(ThemeMode::Dark);
    }
}
