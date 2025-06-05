<?php

namespace App\Filament\Resources\Master\CategoryResource\Enums;


use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Status: int implements HasLabel, HasIcon, HasColor
{
    case ACTIVE   = 1;
    case INACTIVE = 0;

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE   => __('resources/category/strings.table.active'),
            self::INACTIVE => __('resources/category/strings.table.inactive'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE   => 'heroicon-o-check-circle',
            self::INACTIVE => 'heroicon-o-x-circle',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE   => 'success',
            self::INACTIVE => 'danger',
        };
    }
}
