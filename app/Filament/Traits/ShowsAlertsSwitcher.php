<?php

namespace App\Filament\Traits;

use App\Filament\Resources\CalendarRuleResource;
use App\Filament\Resources\NotificationSettingResource;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

trait ShowsAlertsSwitcher
{
    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }

    public function getSubNavigation(): array
    {
        return [
            static::switcherItem(NotificationSettingResource::class, 'notifications', 'heroicon-o-bell'),
            static::switcherItem(CalendarRuleResource::class, 'calendar', 'heroicon-o-calendar-days')
                ->visible(CalendarRuleResource::canViewAny()),
        ];
    }

    protected static function switcherItem(string $resource, string $key, string $icon): NavigationItem
    {
        return NavigationItem::make(__("resources/general/strings.alerts_switcher.{$key}"))
            ->icon($icon)
            ->url($resource::getUrl())
            ->isActiveWhen(fn (): bool => static::getResource() === $resource);
    }
}
