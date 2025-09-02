<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Status: string implements HasColor, HasLabel, HasIcon
{
    case Submitted = 'Submitted';
    case Approved = 'Approved';
    case Draft = 'Draft';

    public function getLabel(): ?string
    {
        return __('resources/purchaseOrder/strings.general.status.' . $this->name);
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Approved => 'success',
            self::Draft => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Submitted => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Draft => 'heroicon-o-pencil-square',
        };
    }
}
