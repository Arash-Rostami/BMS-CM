<?php

namespace App\Filament\Resources\General;

use Filament\Infolists\Components\TextEntry;

class InfoComponents
{

    protected const CSS = [
        'style' => 'position: relative; padding-bottom: 0.5rem; margin-bottom: 0.5rem; border-radius: 2px; background: linear-gradient(to right,rgba(0,0,0,0) 0%,rgba(99,102,241,0.15) 15%,rgba(99,102,241,0.25) 50%,rgba(99,102,241,0.15) 85%,rgba(0,0,0,0) 100%) bottom / 100% 2px no-repeat;'
    ];

    public static function viewProformaInvoices(): TextEntry
    {
        return TextEntry::make('proformaInvoices.formatted_name')
            ->label(__('resources/general/strings.relevant_module.table.proforma_invoices'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->visible(fn($record) => self::relationNotEmpty($record, 'proformaInvoices'));
    }

    public static function viewPurchaseOrders(): TextEntry
    {
        return TextEntry::make('purchaseOrders.formatted_name')
            ->label(__('resources/general/strings.relevant_module.form.purchase_orders'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->visible(fn($record) => self::relationNotEmpty($record, 'purchaseOrders'));
    }

    public static function viewPurchaseRequests(): TextEntry
    {
        return TextEntry::make('purchaseRequests.formatted_name')
            ->label(__('resources/general/strings.relevant_module.form.purchase_requests'))
            ->listWithLineBreaks()
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->visible(fn($record) => self::relationNotEmpty($record, 'purchaseRequests'));
    }

    public static function viewRegisteredOrders(): TextEntry
    {
        return TextEntry::make('registeredOrders.formatted_name')
            ->label(__('resources/general/strings.relevant_module.table.registered_orders'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->visible(fn($record) => self::relationNotEmpty($record, 'registeredOrders'));
    }

    protected static function relationNotEmpty(object|null $record, string $relation): bool
    {
        if (!$record) return false;

        // owner model case: relation method exists
        if (method_exists($record, $relation) && is_callable([$record, $relation])) {
            try {
                return $record->{$relation}()->exists();
            } catch (\Throwable $e) {
                return false;
            }
        }

        // child or loaded relation case: check property/attribute safely
        return collect($record->{$relation} ?? null)->isNotEmpty();
    }

}
