<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Source: string implements HasColor, HasIcon, HasLabel
{
    case PR = 'PR';
    case PO = 'PO';
    case PI = 'PI';
    case None = '-';

    public static function getAllFromRecord($record): array
    {
        $sources = [];

        $hasPI = isset($record->proforma_invoices_count)
            ? ($record->proforma_invoices_count > 0) : $record->proformaInvoices()->exists();

        $hasPR = isset($record->purchase_requests_count)
            ? ($record->purchase_requests_count > 0) : $record->purchaseRequests()->exists();

        $hasPO = isset($record->purchase_orders_count)
            ? ($record->purchase_orders_count > 0) : $record->purchaseOrders()->exists();

        if ($hasPI) {
            $sources[] = self::PI;
        }
        if ($hasPR) {
            $sources[] = self::PR;
        }
        if ($hasPO) {
            $sources[] = self::PO;
        }
        if (empty($sources)) {
            $sources[] = self::None;
        }

        return $sources;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PR => 'warning',
            self::PO => 'success',
            self::PI => 'info',
            self::None => 'gray',
        };
    }

    public static function getFromRecord($record): self
    {
        if ($record->purchaseRequests()->exists()) {
            return self::PR;
        }
        if ($record->purchaseOrders()->exists()) {
            return self::PO;
        }
        if ($record->proformaInvoices()->exists()) {
            return self::PI;
        }

        return self::None;
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PR => 'heroicon-o-shopping-cart',
            self::PO => 'heroicon-o-shopping-bag',
            self::PI => 'heroicon-o-document-text',
            self::None => 'heroicon-o-minus-circle',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PR => 'PR',
            self::PO => 'PO',
            self::PI => 'PI',
            self::None => '-',
        };
    }

    public function getTooltip(): ?string
    {
        $isFa = app()->getLocale() === 'fa';

        return match ($this) {
            self::PR => $isFa
                ? '🔄 ستون درخواست خرید را فعال کنید'
                : '🔄 Toggle Purchase Request Column',
            self::PO => $isFa
                ? '🔄 ستون سفارش خرید را فعال کنید'
                : '🔄 Toggle Purchase Order Column',
            self::PI => $isFa
                ? '🔄 ستون پیش فاکتور خرید را فعال کنید'
                : '🔄 Toggle Proforma Invoice Column',
            default => '',
        };
    }
}
