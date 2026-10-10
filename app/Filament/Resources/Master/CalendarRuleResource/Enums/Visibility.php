<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Visibility: string implements HasColor, HasIcon, HasLabel
{
    case ME = 'me';

    case EVERYONE = 'everyone';

    case USERS = 'users';

    case ROLES = 'roles';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ME => __('resources/calendarRule/strings.visibility.me'),
            self::EVERYONE => __('resources/calendarRule/strings.visibility.everyone'),
            self::USERS => __('resources/calendarRule/strings.visibility.users'),
            self::ROLES => __('resources/calendarRule/strings.visibility.roles'),
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ME => 'heroicon-o-lock-closed',
            self::EVERYONE => 'heroicon-o-globe-alt',
            self::USERS => 'heroicon-o-user-group',
            self::ROLES => 'heroicon-o-identification',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ME => 'gray',
            self::EVERYONE => 'success',
            self::USERS => 'info',
            self::ROLES => 'warning',
        };
    }
}
