<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Services\Country;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;

trait Infolist
{
    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/proformaInvoice/strings.form.attachments'))
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

    public static function viewBeneficiaryCountry(): TextEntry
    {
        return TextEntry::make('beneficiary_country')
            ->label(__('resources/proformaInvoice/strings.form.beneficiary_country'))
            ->icon('heroicon-m-globe-alt')
            ->formatStateUsing(fn ($state) => app(Country::class)->getCountryNameByCode($state) ?? $state);
    }

    public static function viewBuyerCommCardNum(): TextEntry
    {
        return TextEntry::make('buyer_comm_card_num')
            ->label(__('resources/proformaInvoice/strings.form.buyer_comm_card_num'))
            ->icon('heroicon-m-identification');
    }

    public static function viewBuyerCompany(): TextEntry
    {
        return TextEntry::make('buyerCompany.name')
            ->label(__('resources/proformaInvoice/strings.form.buyer_company'))
            ->icon('heroicon-m-building-office');
    }

    public static function viewContractNo(): TextEntry
    {
        return TextEntry::make('contract_no')
            ->copyable()
            ->label(__('resources/proformaInvoice/strings.form.contract_no'))
            ->icon('heroicon-m-clipboard-document-list');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/proformaInvoice/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/proformaInvoice/strings.table.creator'))
            ->icon('heroicon-m-user-circle');
    }

    public static function viewDeliveryTerms(): TextEntry
    {
        return TextEntry::make('delivery_terms')
            ->label(__('resources/proformaInvoice/strings.form.delivery_terms'))
            ->badge()
            ->icon('heroicon-m-cube')
            ->formatStateUsing(fn (?string $state): ?string => $state ? __('resources/proformaInvoice/strings.general.delivery_terms.'.$state) : null);
    }

    public static function viewDestinationCountry(): TextEntry
    {
        return TextEntry::make('destination_country')
            ->label(__('resources/proformaInvoice/strings.form.destination_country'))
            ->icon('heroicon-m-globe-alt')
            ->formatStateUsing(fn ($state) => app(Country::class)->getCountryNameByCode($state) ?? $state);
    }

    public static function viewDiscount(): TextEntry
    {
        return TextEntry::make('discount')
            ->label(__('resources/proformaInvoice/strings.form.discount'))
            ->color('success')
            ->formatStateUsing(fn ($state): string => isset($state) ? delimiter($state) : '');
    }

    public static function viewFreightCharges(): TextEntry
    {
        return TextEntry::make('freight_charges')
            ->label(__('resources/proformaInvoice/strings.form.freight_charges'))
            ->color('success')
            ->formatStateUsing(fn ($state): string => isset($state) ? delimiter($state) : '');
    }

    public static function viewInvoiceDate(): TextEntry
    {
        return TextEntry::make('invoice_date')
            ->label(__('resources/proformaInvoice/strings.form.invoice_date'))
            ->icon('heroicon-m-calendar-days')
            ->formatStateUsing(fn ($state) => app()->getLocale() === 'fa' ? toPersianDate($state) : toGregorianDate($state));
    }

    public static function viewInvoiceItems(): RepeatableEntry
    {
        return RepeatableEntry::make('items')
            ->label(__('resources/proformaInvoice/strings.infolist.invoice_items'))
            ->schema([
                TextEntry::make('product.name')
                    ->label(__('resources/proformaInvoice/strings.form.product'))
                    ->formatStateUsing(fn ($record) => $record->product?->getLocalizedNameAttribute())
                    ->columnSpan(2),
                TextEntry::make('quantity')
                    ->label(__('resources/proformaInvoice/strings.form.quantity')),
                TextEntry::make('unit')
                    ->label(__('resources/proformaInvoice/strings.form.unit'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? __('resources/general/strings.metrics.'.$state) : null),
                TextEntry::make('unit_price')
                    ->label(__('resources/proformaInvoice/strings.form.unit_price'))
                    ->color('success')
                    ->formatStateUsing(fn ($state): string => isset($state) ? delimiter($state) : ''),
                TextEntry::make('total_amount')
                    ->label(__('resources/proformaInvoice/strings.form.item_total_amount'))
                    ->color('success')
                    ->formatStateUsing(fn ($state): string => isset($state) ? delimiter($state) : ''),
                TextEntry::make('origin')
                    ->label(__('resources/proformaInvoice/strings.form.origin')),
                TextEntry::make('hs_code')
                    ->label(__('resources/proformaInvoice/strings.form.hs_code')),
                TextEntry::make('net_weight')
                    ->label(__('resources/proformaInvoice/strings.form.net_weight')),
                TextEntry::make('gross_weight')
                    ->label(__('resources/proformaInvoice/strings.form.gross_weight')),
                TextEntry::make('description')
                    ->label(__('resources/proformaInvoice/strings.form.item_description'))
                    ->columnSpanFull()
                    ->visible(fn ($record) => filled($record->description)),
                TextEntry::make('english_description')
                    ->label(__('resources/proformaInvoice/strings.form.item_english_description'))
                    ->columnSpanFull()
                    ->visible(fn ($record) => filled($record->english_description)),
            ])->columns(5);
    }

    public static function viewInvoiceNo(): TextEntry
    {
        return TextEntry::make('invoice_no')
            ->copyable()
            ->label(__('resources/proformaInvoice/strings.form.invoice_no'))
            ->icon('heroicon-m-hashtag');
    }

    public static function viewMainCurrency(): TextEntry
    {
        return TextEntry::make('mainCurrency.name')
            ->label(__('resources/proformaInvoice/strings.form.main_currency'))
            ->icon('heroicon-m-banknotes');
    }

    public static function viewOriginCountry(): TextEntry
    {
        return TextEntry::make('origin_country')
            ->label(__('resources/proformaInvoice/strings.form.origin_country'))
            ->icon('heroicon-m-globe-alt')
            ->formatStateUsing(fn ($state) => app(Country::class)->getCountryNameByCode($state) ?? $state);
    }

    public static function viewOtherCharges(): TextEntry
    {
        return TextEntry::make('other_charges')
            ->label(__('resources/proformaInvoice/strings.form.other_charges'))
            ->color('success')
            ->formatStateUsing(fn ($state): string => isset($state) ? delimiter($state) : '');
    }

    public static function viewPortOfDischarge(): TextEntry
    {
        return TextEntry::make('port_of_discharge')
            ->label(__('resources/proformaInvoice/strings.form.port_of_discharge'))
            ->icon('heroicon-m-map-pin'); // Using map-pin as anchor might not be in standard set
    }

    public static function viewPortOfLoading(): TextEntry
    {
        return TextEntry::make('port_of_loading')
            ->label(__('resources/proformaInvoice/strings.form.port_of_loading'))
            ->icon('heroicon-m-map-pin');
    }

    public static function viewSecondaryCurrency(): TextEntry
    {
        return TextEntry::make('secondaryCurrency.name')
            ->label(__('resources/proformaInvoice/strings.form.secondary_currency'))
            ->icon('heroicon-m-banknotes');
    }

    public static function viewSellerCompany(): TextEntry
    {
        return TextEntry::make('sellerCompany.name')
            ->label(__('resources/proformaInvoice/strings.form.seller_company'))
            ->icon('heroicon-m-building-office');
    }

    public static function viewShipmentInfo(): TextEntry
    {
        return TextEntry::make('shipment_info')
            ->label(__('resources/proformaInvoice/strings.form.shipment_info'))
            ->icon('heroicon-m-truck')
            ->formatStateUsing(function ($record): string {
                $info = [];
                if ($record->transport_mode) {
                    $info[] = __('resources/proformaInvoice/strings.general.transport_modes.'.$record->transport_mode);
                }
                if ($record->delivery_terms) {
                    $info[] = __('resources/proformaInvoice/strings.general.delivery_terms.'.$record->delivery_terms);
                }

                return implode(' | ', $info);
            });
    }

    public static function viewTotalAmount(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/proformaInvoice/strings.form.total_amount'))
            ->color('success')
            ->formatStateUsing(fn ($state): string => isset($state) ? delimiter($state) : '');
    }

    public static function viewTransportMode(): TextEntry
    {
        return TextEntry::make('transport_mode')
            ->label(__('resources/proformaInvoice/strings.form.transport_mode'))
            ->badge()
            ->icon('heroicon-m-truck')
            ->formatStateUsing(fn (?string $state): ?string => $state ? __('resources/proformaInvoice/strings.general.transport_modes.'.$state) : null);
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/proformaInvoice/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/proformaInvoice/strings.table.updater'))
            ->icon('heroicon-m-pencil-square');
    }

    public static function viewValidityDate(): TextEntry
    {
        return TextEntry::make('validity_date')
            ->label(__('resources/proformaInvoice/strings.form.validity_date'))
            ->icon('heroicon-m-calendar-days')
            ->formatStateUsing(fn ($state) => app()->getLocale() === 'fa' ? toPersianDate($state) : toGregorianDate($state));
    }
}
