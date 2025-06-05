<?php

namespace App\Filament\Resources\Master\UserResource\Enums;


use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
enum PositionStatus: string implements HasLabel, HasIcon, HasColor
{
    case JNR = 'jnr';
    case MDR = 'mdr';
    case SNR = 'snr';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::JNR => __('resources/user/strings.general.options.jnr'),
            self::MDR => __('resources/user/strings.general.options.mdr'),
            self::SNR => __('resources/user/strings.general.options.snr'),
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::JNR => 'heroicon-o-user',
            self::MDR => 'heroicon-o-user-group',
            self::SNR => 'heroicon-o-star',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::JNR => 'info',
            self::MDR => 'primary',
            self::SNR => 'success',
        };
    }
}
