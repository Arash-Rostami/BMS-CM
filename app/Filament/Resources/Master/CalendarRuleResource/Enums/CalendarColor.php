<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CalendarColor: string implements HasColor, HasLabel
{
    case SKY = 'sky';

    case EMERALD = 'emerald';

    case AMBER = 'amber';

    case ROSE = 'rose';

    case VIOLET = 'violet';

    case TEAL = 'teal';

    case ORANGE = 'orange';

    case SLATE = 'slate';

    public function getLabel(): ?string
    {
        return __("resources/calendarRule/strings.color.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return $this->value;
    }

    public function cssVar(): string
    {
        return "--cal-color-{$this->value}";
    }
}
