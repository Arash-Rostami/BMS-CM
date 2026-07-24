<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Source: string implements HasColor, HasIcon, HasLabel
{
    case PR = 'PR';
    case RO = 'RO';
    case PI = 'PI';
    case None = '-';

    public static function getAllFromRecord($record): array
    {
        $sources = [];

        $hasPI = isset($record->proforma_invoices_count)
            ? ($record->proforma_invoices_count > 0) : $record->proformaInvoices()->exists();

        $hasPR = isset($record->purchase_requests_count)
            ? ($record->purchase_requests_count > 0) : $record->purchaseRequests()->exists();

        $hasRO = isset($record->registered_orders_count)
            ? ($record->registered_orders_count > 0) : $record->registeredOrders()->exists();

        if ($hasPI) {
            $sources[] = self::PI;
        }
        if ($hasPR) {
            $sources[] = self::PR;
        }
        if ($hasRO) {
            $sources[] = self::RO;
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
            self::RO => 'success',
            self::PI => 'info',
            self::None => 'gray',
        };
    }

    public static function getFromRecord($record): self
    {
        if ($record->purchaseRequests()->exists()) {
            return self::PR;
        }
        if ($record->registeredOrders()->exists()) {
            return self::RO;
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
            self::RO => 'heroicon-o-document-check',
            self::PI => 'heroicon-o-document-text',
            self::None => 'heroicon-o-minus-circle',
        };
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PR => 'PR',
            self::RO => 'RO',
            self::PI => 'PI',
            self::None => '-',
        };
    }

    public function getTooltip(): ?string
    {
        return match ($this) {
            self::PR => __('resources/purchaseOrder/strings.source.tooltip.pr'),
            self::RO => __('resources/purchaseOrder/strings.source.tooltip.ro'),
            self::PI => __('resources/purchaseOrder/strings.source.tooltip.pi'),
            default => null,
        };
    }
}
