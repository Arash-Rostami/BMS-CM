<?php

namespace App\Filament\Resources\Master\ProductResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InStockStatus: int implements HasColor, HasIcon, HasLabel
{
    case IN_STOCK = 1;
    case OUT_OF_STOCK = 0;

    public function getLabel(): string
    {
        return match ($this) {
            self::IN_STOCK => '✅',
            self::OUT_OF_STOCK => '❌',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::IN_STOCK => 'heroicon-o-check-circle',
            self::OUT_OF_STOCK => 'heroicon-o-x-circle',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::IN_STOCK => 'success',
            self::OUT_OF_STOCK => 'danger',
        };
    }
}
