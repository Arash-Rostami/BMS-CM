<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Source: string implements HasColor, HasLabel, HasIcon
{
    case PI = 'PI';
    case RO = 'RO';
    case PO = 'PO';
    case None = '-';

    public static function getAllFromRecord($record): array
    {
        $sources = [];

        $hasPI = isset($record->proforma_invoices_count)
            ? ($record->proforma_invoices_count > 0) : $record->proformaInvoices()->exists();

        $hasRO = isset($record->registered_orders_count)
            ? ($record->registered_orders_count > 0) : $record->registeredOrders()->exists();

        $hasPO = isset($record->purchase_orders_count)
            ? ($record->purchase_orders_count > 0) : $record->purchaseOrders()->exists();

        if ($hasPI) $sources[] = self::PI;
        if ($hasRO) $sources[] = self::RO;
        if ($hasPO) $sources[] = self::PO;
        if (empty($sources)) $sources[] = self::None;

        return $sources;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PI => 'warning',
            self::RO => 'success',
            self::PO => 'info',
            self::None => 'gray',
        };
    }

    public static function getFromRecord($record): self
    {
        if ($record->proformaInvoices()->exists()) return self::PI;
        if ($record->registeredOrders()->exists()) return self::RO;
        if ($record->purchaseOrders()->exists()) return self::PO;

        return self::None;
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PI => 'heroicon-o-document-text',
            self::RO => 'heroicon-o-document-check',
            self::PO => 'heroicon-o-shopping-bag',
            self::None => 'heroicon-o-minus-circle',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PI => 'PI',
            self::RO => 'RO',
            self::PO => 'PO',
            self::None => '-',
        };
    }

    public function getTooltip(): ?string
    {
        $isFa = app()->getLocale() === 'fa';

        return match ($this) {
            self::PI => $isFa
                ? '🔄 ستون پیش فاکتور را فعال کنید'
                : '🔄 Toggle Proforma Invoice Column',
            self::RO => $isFa
                ? '🔄 ستون سفارش ثبت شده را فعال کنید'
                : '🔄 Toggle Registered Order Column',
            self::PO => $isFa
                ? '🔄 ستون ثبت سفارش  را فعال کنید'
                : '🔄 Toggle Purchase Order Column',
            default => '',
        };
    }
}
