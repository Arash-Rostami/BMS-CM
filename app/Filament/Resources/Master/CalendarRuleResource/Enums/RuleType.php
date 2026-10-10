<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RuleType: string implements HasColor, HasIcon, HasLabel
{
    case HEADS_UP = 'heads_up';

    case ACTION = 'action';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::HEADS_UP => __('resources/calendarRule/strings.type.heads_up'),
            self::ACTION => __('resources/calendarRule/strings.type.action'),
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::HEADS_UP => 'heroicon-o-bell',
            self::ACTION => 'heroicon-o-exclamation-triangle',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::HEADS_UP => 'info',
            self::ACTION => 'warning',
        };
    }
}
