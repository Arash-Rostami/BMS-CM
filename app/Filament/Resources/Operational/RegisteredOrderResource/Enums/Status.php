<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Status: string implements HasColor, HasLabel, HasIcon
{
    case Submitted = 'Submitted';
    case Draft = 'Draft';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Draft => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Submitted => 'heroicon-o-clock',
            self::Draft => 'heroicon-o-pencil-square',
        };
    }

    public function getLabel(): ?string
    {
        return __('resources/registeredOrder/strings.general.status.' . $this->name);
    }
}
