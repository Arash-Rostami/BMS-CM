<?php

namespace App\Filament\Resources\Operational\CustomResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum GuaranteeStatus: string implements HasColor, HasIcon, HasLabel
{
    case Returned = 'Returned';
    case NotReturned = 'Not Returned';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Returned => 'success',
            self::NotReturned => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Returned => 'heroicon-o-arrow-uturn-left',
            self::NotReturned => 'heroicon-o-x-circle',
        };
    }

    public function getLabel(): ?string
    {
        return __('resources/custom/strings.general.bank_guarantee_status.'.str($this->name)->snake());
    }
}
