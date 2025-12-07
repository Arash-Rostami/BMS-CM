<?php

namespace App\Filament\Resources\Operational\CustomResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CommitmentStatus: string implements HasColor, HasLabel, HasIcon
{
    case Completed = 'Completed';
    case NotCompleted = 'Not Completed';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Completed => 'success',
            self::NotCompleted => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Completed => 'heroicon-o-check-circle',
            self::NotCompleted => 'heroicon-o-clock',
        };
    }

    public function getLabel(): ?string
    {
        return __('resources/custom/strings.general.commitment_status.' . str($this->name)->snake());
    }
}
