<?php

namespace App\Filament\Resources\Operational\CustomResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ClearanceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Declared = 'Declared';
    case DocumentsSubmitted = 'Documents Submitted';
    case PendingPayment = 'Pending Payment';
    case PendingEditing = 'Pending Editing';
    case ExitGate = 'Exit Gate';
    case TenPercentRemaining = '10% Remaining';
    case Appraisal = 'Appraisal';
    case Completed = 'Completed';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Declared => 'primary',
            self::DocumentsSubmitted => 'info',
            self::PendingPayment => 'warning',
            self::PendingEditing => 'gray',
            self::ExitGate => 'success',
            self::TenPercentRemaining => 'danger',
            self::Appraisal => 'primary',
            self::Completed => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Declared => 'heroicon-o-clipboard-document-check',
            self::DocumentsSubmitted => 'heroicon-o-document-arrow-up',
            self::PendingPayment => 'heroicon-o-banknotes',
            self::PendingEditing => 'heroicon-o-pencil-square',
            self::ExitGate => 'heroicon-o-truck',
            self::TenPercentRemaining => 'heroicon-o-pie-chart',
            self::Appraisal => 'heroicon-o-magnifying-glass',
            self::Completed => 'heroicon-o-check-badge',
        };
    }

    public function getLabel(): ?string
    {
        return __('resources/custom/strings.general.clearance_status.'.str($this->name)->snake());
    }
}
