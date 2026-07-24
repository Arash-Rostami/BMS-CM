<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;

trait Infolist
{
    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/registeredOrder/strings.form.attachments'))
            ->schema([
                TextEntry::make('path')
                    ->hiddenLabel()
                    ->formatStateUsing(fn (string $state): string => basename($state))
                    ->tooltip(fn ($record) => $record->name ?? '')
                    ->icon('heroicon-m-paper-clip')
                    ->color('primary')
                    ->url(fn ($record): string => Storage::disk('public')->url($record->path), shouldOpenInNewTab: true),
            ])
            ->columns(1);
    }

    public static function viewBuyer(): TextEntry
    {
        return TextEntry::make('buyerCompany.name')
            ->label(__('resources/registeredOrder/strings.form.buyer'))
            ->icon('heroicon-m-building-office')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/registeredOrder/strings.infolist.created_at'))
            ->dateTime()
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreatedAtSimple(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/registeredOrder/strings.infolist.created_at'))
            ->dateTime()
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('user.name')
            ->label(__('resources/registeredOrder/strings.infolist.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewCtNumber(): TextEntry
    {
        return TextEntry::make('contract_no')
            ->label(__('resources/registeredOrder/strings.form.contract_number'))
            ->copyable()
            ->icon('heroicon-m-document-text');
    }

    public static function viewCurrency(): TextEntry
    {
        return TextEntry::make('currency.name')
            ->label(__('resources/registeredOrder/strings.form.currency'))
            ->icon('heroicon-m-banknotes')
            ->placeholder('-');
    }

    public static function viewExpectedDeliveryDate(): TextEntry
    {
        return TextEntry::make('expected_delivery_date')
            ->label(__('resources/registeredOrder/strings.form.expected_delivery_date'))
            ->date()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewIncoterms(): TextEntry
    {
        return TextEntry::make('incoterms')
            ->label(__('resources/registeredOrder/strings.form.incoterms'))
            ->icon('heroicon-m-truck')
            ->placeholder('-');
    }

    public static function viewItemGrossWeight(): TextEntry
    {
        return TextEntry::make('gross_weight')
            ->label(__('resources/registeredOrder/strings.infolist.item_gross_weight'))
            ->icon('heroicon-m-scale');
    }

    public static function viewItemNetWeight(): TextEntry
    {
        return TextEntry::make('net_weight')
            ->label(__('resources/registeredOrder/strings.infolist.item_net_weight'))
            ->icon('heroicon-m-scale');
    }

    public static function viewItemProduct(): TextEntry
    {
        return TextEntry::make('product.name')
            ->label(__('resources/registeredOrder/strings.infolist.item_product'))
            ->icon('heroicon-m-cube')
            ->columnSpan(2);
    }

    public static function viewItemQuantity(): TextEntry
    {
        return TextEntry::make('quantity')
            ->label(__('resources/registeredOrder/strings.infolist.item_quantity'));
    }

    public static function viewItemUnit(): TextEntry
    {
        return TextEntry::make('unit')
            ->label(__('resources/registeredOrder/strings.infolist.item_unit'))
            ->badge()
            ->color('gray');
    }

    public static function viewItemUnitPrice(): TextEntry
    {
        return TextEntry::make('unit_price')
            ->label(__('resources/registeredOrder/strings.infolist.item_unit_price'))
            ->color('success')
            ->formatStateUsing(fn ($state) => $state ? delimiter($state) : '-');
    }

    public static function viewNotes(): TextEntry
    {
        return TextEntry::make('notes')
            ->label(__('resources/registeredOrder/strings.form.notes'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewOfficialRegistrationNo(): TextEntry
    {
        return TextEntry::make('official_registration_no')
            ->label(__('resources/registeredOrder/strings.form.official_registration_no'))
            ->copyable()
            ->icon('heroicon-m-clipboard-document-check')
            ->placeholder('-');
    }

    public static function viewOrderDate(): TextEntry
    {
        return TextEntry::make('order_date')
            ->label(__('resources/registeredOrder/strings.form.order_date'))
            ->date()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewPackingDetails(): TextEntry
    {
        return TextEntry::make('packing_details')
            ->label(__('resources/registeredOrder/strings.form.packing_details'))
            ->icon('heroicon-m-archive-box')
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewRoNumber(): TextEntry
    {
        return TextEntry::make('ro_number')
            ->label(__('resources/registeredOrder/strings.form.ro_number'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-hashtag')
            ->copyable();
    }

    public static function viewSeller(): TextEntry
    {
        return TextEntry::make('sellerCompany.name')
            ->label(__('resources/registeredOrder/strings.form.seller'))
            ->icon('heroicon-m-building-office')
            ->placeholder('-');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status.name')
            ->label(__('resources/registeredOrder/strings.form.status'))
            ->badge()
            ->formatStateUsing(fn ($record): ?string => $record->status?->getLocalizedNameAttribute())
            ->placeholder('-');
    }

    public static function viewTotalAmount(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/registeredOrder/strings.infolist.total_amount'))
            ->color('success')
            ->formatStateUsing(fn ($state) => $state !== null ? delimiter($state) : '-')
            ->placeholder('0.00');
    }

    public static function viewTotalQuantity(): TextEntry
    {
        return TextEntry::make('total_quantity')
            ->label(__('resources/registeredOrder/strings.form.total_quantity'))
            ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 0) : '-')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/registeredOrder/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/registeredOrder/strings.infolist.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewValidityDate(): TextEntry
    {
        return TextEntry::make('validity_date')
            ->label(__('resources/registeredOrder/strings.form.validity_date'))
            ->date()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }
}
