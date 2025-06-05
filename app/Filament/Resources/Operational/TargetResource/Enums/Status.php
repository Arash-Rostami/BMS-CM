<?php

namespace App\Filament\Resources\Operational\TargetResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Status: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Achieved = 'achieved';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => __('resources/target/strings.status.active'),
            self::Inactive => __('resources/target/strings.status.inactive'),
            self::Achieved => __('resources/target/strings.status.achieved'),
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'danger',
            self::Achieved => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-x-circle',
            self::Achieved => 'heroicon-o-trophy',
        };
    }
}
