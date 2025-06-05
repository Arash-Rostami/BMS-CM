<?php

namespace App\Filament\Resources\Master\UserResource\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum UserRole: string implements HasLabel, HasIcon, HasColor
{
    case AGENT = 'agent';
    case ACCOUNTANT = 'accountant';
    case MANAGER = 'manager';
    case PARTNER = 'partner';
    case ADMIN = 'admin';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::AGENT => __('resources/user/strings.general.options.agent'),
            self::ACCOUNTANT => __('resources/user/strings.general.options.accountant'),
            self::MANAGER => __('resources/user/strings.general.options.manager'),
            self::PARTNER => __('resources/user/strings.general.options.partner'),
            self::ADMIN => __('resources/user/strings.general.options.admin'),
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::AGENT => 'heroicon-o-pencil',
            self::ACCOUNTANT => 'heroicon-o-calculator',
            self::MANAGER => 'heroicon-o-briefcase',
            self::PARTNER => 'heroicon-o-book-open',
            self::ADMIN => 'heroicon-o-shield-check',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AGENT => 'primary',
            self::ACCOUNTANT => 'warning',
            self::MANAGER => 'success',
            self::PARTNER => 'info',
            self::ADMIN => 'danger',
        };
    }
}
