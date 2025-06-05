<?php

namespace App\Filament\Resources\Master\CategoryResource\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Level: string implements HasLabel, HasIcon, HasColor
{
    case BASE = 'base';
    case SUB = 'sub';
    case LINE = 'line';
    case MODEL = 'model';
    case OTHER = 'other';

    public static function fromLevel(int $level): self
    {
        return match ($level) {
            0 => self::BASE,
            1 => self::SUB,
            2 => self::LINE,
            3 => self::MODEL,
            default => self::OTHER,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::BASE => __('resources/category/strings.general.options.base_level'),
            self::SUB => __('resources/category/strings.general.options.sub_level'),
            self::LINE => __('resources/category/strings.general.options.line'),
            self::MODEL => __('resources/category/strings.general.options.model'),
            self::OTHER => '❹+',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::BASE => 'heroicon-s-cube',
            self::SUB, self::LINE, self::MODEL, self::OTHER => 'heroicon-c-puzzle-piece',
        };
    }

    public function getColor(): string|array|null
    {
        return 'primary';
    }
}
