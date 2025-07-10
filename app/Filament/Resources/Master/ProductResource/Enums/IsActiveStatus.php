<?php

namespace App\Filament\Resources\Master\ProductResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum IsActiveStatus: int implements HasLabel, HasIcon, HasColor
{

    case IS_ACTIVE = 1;
    case IS_IN_ACTIVE = 0;

    public function getLabel(): string
    {
        return match ($this) {
            self::IS_ACTIVE => '✅',
            self::IS_IN_ACTIVE => '❌',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::IS_ACTIVE => 'heroicon-o-check-circle',
            self::IS_IN_ACTIVE => 'heroicon-o-x-circle',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::IS_ACTIVE => 'success',
            self::IS_IN_ACTIVE => 'danger',
        };
    }
}
