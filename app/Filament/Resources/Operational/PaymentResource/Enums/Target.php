<?php

namespace App\Filament\Resources\Operational\PaymentResource\Enums;

use App\Models\BankProfile;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Target: string implements HasColor, HasIcon, HasLabel
{
    case PO = PurchaseOrder::class;
    case RO = RegisteredOrder::class;
    case BP = BankProfile::class;
    case None = '-';

    public static function getFromRecord($record): self
    {
        return match ($record->targetable_type) {
            PurchaseOrder::class => self::PO,
            RegisteredOrder::class => self::RO,
            BankProfile::class => self::BP,
            default => self::None,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PO => 'success',
            self::RO => 'info',
            self::BP => 'warning',
            self::None => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PO => 'heroicon-o-shopping-bag',
            self::RO => 'heroicon-o-document-check',
            self::BP => 'heroicon-o-building-office',
            self::None => 'heroicon-o-minus-circle',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PO => 'PO',
            self::RO => 'RO',
            self::BP => 'BP',
            self::None => '-',
        };
    }

    public function getTooltip(): ?string
    {
        $isFa = app()->getLocale() === 'fa';

        return match ($this) {
            self::PO => $isFa
                ? '🔄 ستون مرتبط (سفارش خرید) را فعال کنید'
                : '🔄 Toggle Relevant (Purchase Order) Column',
            self::RO => $isFa
                ? '🔄 ستون مرتبط (ثبت سفارش) را فعال کنید'
                : '🔄 Toggle Relevant (Registered Order) Column',
            self::BP => $isFa
                ? '🔄 ستون مرتبط (پروفایل بانکی) را فعال کنید'
                : '🔄 Toggle Relevant (Bank Profile) Column',
            default => '',
        };
    }
}
