<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Status: string implements HasColor, HasIcon, HasLabel
{
    case Allocated = 'Allocated';
    case AwaitingSupply = 'Awaiting Supply';
    case Purchased = 'Purchased';
    case Received = 'Received';
    case ReadyForAllocation = 'Ready for Allocation';
    case UnderEditing = 'Under Editing';
    case AwaitingBankReview = 'Awaiting Bank Review';
    case Rejected = 'Rejected';
    case DocumentaryBill = 'Documentary Bill';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Allocated => 'info',
            self::AwaitingSupply => 'warning',
            self::Purchased => 'success',
            self::Received => 'success',
            self::ReadyForAllocation => 'info',
            self::UnderEditing => 'gray',
            self::AwaitingBankReview => 'warning',
            self::Rejected => 'danger',
            self::DocumentaryBill => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Allocated => 'heroicon-o-inbox-arrow-down',
            self::AwaitingSupply => 'heroicon-o-banknotes',
            self::Purchased => 'heroicon-o-shopping-cart',
            self::Received => 'heroicon-o-truck',
            self::ReadyForAllocation => 'heroicon-o-check-circle',
            self::UnderEditing => 'heroicon-o-pencil-square',
            self::AwaitingBankReview => 'heroicon-o-clock',
            self::Rejected => 'heroicon-o-x-circle',
            self::DocumentaryBill => 'heroicon-o-document-check',
        };
    }

    public function getLabel(): ?string
    {
        $key = str($this->name)->snake(' ')->title()->toString();

        $cases = [
            'Awaiting Supply' => 'AwaitingSupply',
            'Ready For Allocation' => 'ReadyForAllocation',
            'Under Editing' => 'UnderEditing',
            'Awaiting Bank Review' => 'AwaitingBankReview',
            'Documentary Bill' => 'DocumentaryBill',
        ];

        $key = $cases[$key] ?? $key;

        return __('resources/bankProfile/strings.general.status.'.$key);
    }
}
