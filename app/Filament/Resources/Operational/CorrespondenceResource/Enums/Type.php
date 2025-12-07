<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Type: string implements HasLabel, HasIcon, HasColor
{
    case REPORT = 'report';
    case INQUIRY = 'inquiry';
    case WARNING = 'warning';
    case NOTE = 'note';
    case ANNOUNCEMENT = 'announcement';

    public function getLabel(): string
    {
        return __('resources/correspondence/strings.enums.type.' . $this->value);
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::REPORT => 'heroicon-o-clipboard-document-list',
            self::INQUIRY => 'heroicon-o-question-mark-circle',
            self::WARNING => 'heroicon-o-exclamation-triangle',
            self::NOTE => 'heroicon-o-pencil-square',
            self::ANNOUNCEMENT => 'heroicon-o-megaphone',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::REPORT => 'info',
            self::INQUIRY => 'primary',
            self::WARNING => 'danger',
            self::NOTE => 'gray',
            self::ANNOUNCEMENT => 'warning',
        };
    }
}
