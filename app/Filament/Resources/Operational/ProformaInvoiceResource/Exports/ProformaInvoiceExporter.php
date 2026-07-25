<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\ProformaInvoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class ProformaInvoiceExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = ProformaInvoice::class;

    protected static function eagerLoadRelations(): array
    {
        return ['sellerCompany', 'buyerCompany', 'mainCurrency', 'secondaryCurrency', 'items.product'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/proformaInvoice/strings.export.id')),
            ExportColumn::make('invoice_no')->label(__('resources/proformaInvoice/strings.export.invoice_number')),
            ExportColumn::make('invoice_date')->label(__('resources/proformaInvoice/strings.export.invoice_date')),
            ExportColumn::make('validity_date')->label(__('resources/proformaInvoice/strings.export.validity_date')),
            ExportColumn::make('sellerCompany.name')->label(__('resources/proformaInvoice/strings.export.seller')),
            ExportColumn::make('sellerCompany.english_name')->label(__('resources/proformaInvoice/strings.export.seller_english')),
            ExportColumn::make('buyerCompany.name')->label(__('resources/proformaInvoice/strings.export.buyer')),
            ExportColumn::make('buyerCompany.english_name')->label(__('resources/proformaInvoice/strings.export.buyer_english')),
            ExportColumn::make('discount')->label(__('resources/proformaInvoice/strings.export.discount'))
                ->formatStateUsing(fn ($state) => preciseNumber($state)),
            ExportColumn::make('freight_charges')->label(__('resources/proformaInvoice/strings.export.freight_charges'))
                ->formatStateUsing(fn ($state) => preciseNumber($state)),
            ExportColumn::make('other_charges')->label(__('resources/proformaInvoice/strings.export.other_charges'))
                ->formatStateUsing(fn ($state) => preciseNumber($state)),
            ExportColumn::make('total_amount')->label(__('resources/proformaInvoice/strings.export.total_amount'))
                ->formatStateUsing(fn ($state) => preciseNumber($state)),
            ExportColumn::make('mainCurrency.name')->label(__('resources/proformaInvoice/strings.export.main_currency')),
            ExportColumn::make('mainCurrency.english_name')->label(__('resources/proformaInvoice/strings.export.main_currency_english')),
            ExportColumn::make('secondaryCurrency.name')->label(__('resources/proformaInvoice/strings.export.secondary_currency')),
            ExportColumn::make('secondaryCurrency.english_name')->label(__('resources/proformaInvoice/strings.export.secondary_currency_english')),
            ExportColumn::make('delivery_terms')->label(__('resources/proformaInvoice/strings.export.delivery_terms')),
            ExportColumn::make('transport_mode')->label(__('resources/proformaInvoice/strings.export.transport_mode')),
            ExportColumn::make('contract_no')->label(__('resources/proformaInvoice/strings.export.contract_no')),
            ExportColumn::make('buyer_comm_card_num')->label(__('resources/proformaInvoice/strings.export.buyer_commercial_card_number')),
            ExportColumn::make('origin_country')->label(__('resources/proformaInvoice/strings.export.country_of_origin')),
            ExportColumn::make('destination_country')->label(__('resources/proformaInvoice/strings.export.country_of_destination')),
            ExportColumn::make('beneficiary_country')->label(__('resources/proformaInvoice/strings.export.beneficiary_country')),
            ExportColumn::make('port_of_loading')->label(__('resources/proformaInvoice/strings.export.port_of_loading')),
            ExportColumn::make('port_of_discharge')->label(__('resources/proformaInvoice/strings.export.port_of_discharge')),

            ExportColumn::make('items')
                ->label(__('resources/proformaInvoice/strings.export.items'))
                ->state(function (ProformaInvoice $record): string {
                    return $record->items->map(function ($item) {
                        $product = $item->product?->getLocalizedNameAttribute() ?? 'N/A';
                        $quantity = preciseNumber($item->quantity);
                        $price = preciseNumber($item->unit_price);
                        $total = preciseNumber($item->total_amount);
                        $hsCode = $item->hs_code ? " (HS: {$item->hs_code})" : '';

                        return "- {$product}, Qty: {$quantity}, Price: {$price}, Total: {$total}{$hsCode}";
                    })->implode("\n");
                }),

            ExportColumn::make('creator.name')->label(__('resources/proformaInvoice/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/proformaInvoice/strings.export.updater')),
            ExportColumn::make('created_at')->label(__('resources/proformaInvoice/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/proformaInvoice/strings.export.updated_at')),
        ];
    }
}
