<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Status: string implements HasColor, HasIcon, HasLabel
{
    case Submitted = 'Submitted';
    case UnderReview = 'Under Review';
    case SalesManagerApproval = 'Sales Manager Approval';
    case Authorized = 'Authorized';
    case Declined = 'Declined';
    case Conditional = 'Conditional';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::UnderReview => 'gray',
            self::SalesManagerApproval => 'warning',
            self::Authorized => 'success',
            self::Declined => 'danger',
            self::Conditional => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Submitted => 'heroicon-o-paper-airplane',
            self::UnderReview => 'heroicon-o-eye',
            self::SalesManagerApproval => 'heroicon-o-clock',
            self::Authorized => 'heroicon-o-check-circle',
            self::Declined => 'heroicon-o-x-circle',
            self::Conditional => 'heroicon-o-exclamation-triangle',
        };
    }

    public function getLabel(): ?string
    {
        return __('resources/purchaseRequest/strings.general.status.'.$this->name);
    }
}
