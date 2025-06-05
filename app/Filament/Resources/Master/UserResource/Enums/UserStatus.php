<?php

namespace App\Filament\Resources\Master\UserResource\Enums;



use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum UserStatus: string implements HasLabel, HasIcon, HasColor
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACTIVE => __('resources/user/strings.general.options.active'),
            self::INACTIVE => __('resources/user/strings.general.options.inactive'),
            self::PENDING => __('resources/user/strings.general.options.pending'),
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'heroicon-m-check-badge',
            self::INACTIVE => 'heroicon-o-x-circle',
            self::PENDING => 'heroicon-o-question-mark-circle',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'danger',
            self::PENDING => 'warning',
        };
    }

    public static function toSelectOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }
        return $options;
    }
}
