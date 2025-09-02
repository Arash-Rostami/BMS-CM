<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;

trait InfoList
{
    public static function viewBuyer(): TextEntry
    {
        return TextEntry::make('buyer.name')
            ->label(__('resources/purchaseOrder/strings.form.buyer'))
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/purchaseOrder/strings.infolist.created_at'))
            ->dateTime()
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('user.name')
            ->label(__('resources/purchaseOrder/strings.infolist.creator'))
            ->placeholder('-');
    }

    public static function viewCurrency(): TextEntry
    {
        return TextEntry::make('currency.name')
            ->label(__('resources/purchaseOrder/strings.form.currency'))
            ->placeholder('-');
    }

    public static function viewExpectedDeliveryDate(): TextEntry
    {
        return TextEntry::make('expected_delivery_date')
            ->label(__('resources/purchaseOrder/strings.form.expected_delivery_date'))
            ->date()
            ->placeholder('-');
    }

    public static function viewIncoterms(): TextEntry
    {
        return TextEntry::make('incoterms')
            ->label(__('resources/purchaseOrder/strings.form.incoterms'))
            ->badge()
            ->placeholder('-');
    }

    public static function viewNotes(): TextEntry
    {
        return TextEntry::make('notes')
            ->label(__('resources/purchaseOrder/strings.form.notes'))
            ->markdown()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewOrderDate(): TextEntry
    {
        return TextEntry::make('order_date')
            ->label(__('resources/purchaseOrder/strings.form.order_date'))
            ->date()
            ->placeholder('-');
    }

    public static function viewPackingDetails(): TextEntry
    {
        return TextEntry::make('packing_details')
            ->label(__('resources/purchaseOrder/strings.form.packing_details'))
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewPoNumber(): TextEntry
    {
        return TextEntry::make('po_number')
            ->label(__('resources/purchaseOrder/strings.form.po_number'))
            ->copyable();
    }


    public static function viewItemProduct(): TextEntry
    {
        return TextEntry::make('product.name')
            ->label(__('resources/purchaseOrder/strings.infolist.item_product'))
            ->columnSpan(2);
    }

    public static function viewItemQuantity(): TextEntry
    {
        return TextEntry::make('quantity')
            ->label(__('resources/purchaseOrder/strings.infolist.item_quantity'));
    }

    public static function viewItemUnit(): TextEntry
    {
        return TextEntry::make('unit')
            ->label(__('resources/purchaseOrder/strings.infolist.item_unit'));
    }

    public static function viewItemUnitPrice(): TextEntry
    {
        return TextEntry::make('unit_price')
            ->label(__('resources/purchaseOrder/strings.infolist.item_unit_price'))
            ->money(fn($record) => $record->purchaseOrder?->currency?->symbol ?? '');
    }

    public static function viewItemNetWeight(): TextEntry
    {
        return TextEntry::make('net_weight')
            ->label(__('resources/purchaseOrder/strings.infolist.item_net_weight'));
    }

    public static function viewItemGrossWeight(): TextEntry
    {
        return TextEntry::make('gross_weight')
            ->label(__('resources/purchaseOrder/strings.infolist.item_gross_weight'));
    }

    public static function viewShippingAddress(): TextEntry
    {
        return TextEntry::make('shipping_address')
            ->label(__('resources/purchaseOrder/strings.form.shipping_address'))
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status.name')
            ->label(__('resources/purchaseOrder/strings.form.status'))
            ->badge()
            ->placeholder('-');
    }

    public static function viewSupplier(): TextEntry
    {
        return TextEntry::make('supplier.name')
            ->label(__('resources/purchaseOrder/strings.form.supplier'))
            ->placeholder('-');
    }

    public static function viewTotalAmount(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/purchaseOrder/strings.infolist.total_amount'))
            ->formatStateUsing(fn($state, $record) => $state !== null
                ? ($record->currency?->symbol ?? '$') . ' ' . number_format($state, 2)
                : '-'
            )
            ->placeholder('0.00');
    }

    public static function viewTotalQuantity(): TextEntry
    {
        return TextEntry::make('total_quantity')
            ->label(__('resources/purchaseOrder/strings.form.total_quantity'))
            ->formatStateUsing(fn($state) => $state !== null ? number_format($state, 0) : '-')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/purchaseOrder/strings.infolist.updated_at'))
            ->dateTime()
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/purchaseOrder/strings.infolist.updater'))
            ->placeholder('-');
    }

    public static function viewValidityDate(): TextEntry
    {
        return TextEntry::make('validity_date')
            ->label(__('resources/purchaseOrder/strings.form.validity_date'))
            ->date()
            ->placeholder('-');
    }

    // Attachments
    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/proformaInvoice/strings.form.attachments'))
            ->schema([
                TextEntry::make('path')
                    ->label('')
                    ->formatStateUsing(fn(string $state): string => basename($state))
                    ->tooltip(fn($record) => $record->name ?? '')
                    ->icon('heroicon-o-document-text')
                    ->url(fn($record): string => Storage::disk('public')->url($record->path), shouldOpenInNewTab: true),
            ])
            ->columns(1);
    }
}
