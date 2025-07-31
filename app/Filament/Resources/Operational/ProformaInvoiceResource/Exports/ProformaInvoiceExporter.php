<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\ProformaInvoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProformaInvoiceExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = ProformaInvoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('invoice_no')->label(__('resources/proformaInvoice/strings.form.invoice_no')),
            ExportColumn::make('invoice_date')->label(__('resources/proformaInvoice/strings.form.invoice_date')),
            ExportColumn::make('validity_date')->label(__('resources/proformaInvoice/strings.form.validity_date')),
            ExportColumn::make('sellerCompany.name')->label(__('resources/proformaInvoice/strings.form.seller_company')),
            ExportColumn::make('consigneeCompany.name')->label(__('resources/proformaInvoice/strings.form.consignee_company')),
            ExportColumn::make('discount')->label(__('resources/proformaInvoice/strings.form.discount')),
            ExportColumn::make('freight_charges')->label(__('resources/proformaInvoice/strings.form.freight_charges')),
            ExportColumn::make('other_charges')->label(__('resources/proformaInvoice/strings.form.other_charges')),
            ExportColumn::make('total_cfr_amount')->label(__('resources/proformaInvoice/strings.form.total_cfr_amount')),
            ExportColumn::make('mainCurrency.name')->label(__('resources/proformaInvoice/strings.form.main_currency')),
            ExportColumn::make('secondaryCurrency.name')->label(__('resources/proformaInvoice/strings.form.secondary_currency')),
            ExportColumn::make('delivery_terms')->label(__('resources/proformaInvoice/strings.form.delivery_terms')),
            ExportColumn::make('transport_mode')->label(__('resources/proformaInvoice/strings.form.transport_mode')),
            ExportColumn::make('contract_no')->label(__('resources/proformaInvoice/strings.form.contract_no')),
            ExportColumn::make('buyer_comm_card_num')->label(__('resources/proformaInvoice/strings.form.buyer_comm_card_num')),
            ExportColumn::make('origin_country')->label(__('resources/proformaInvoice/strings.form.origin_country')),
            ExportColumn::make('destination_country')->label(__('resources/proformaInvoice/strings.form.destination_country')),
            ExportColumn::make('beneficiary_country')->label(__('resources/proformaInvoice/strings.form.beneficiary_country')),
            ExportColumn::make('port_of_loading')->label(__('resources/proformaInvoice/strings.form.port_of_loading')),
            ExportColumn::make('port_of_discharge')->label(__('resources/proformaInvoice/strings.form.port_of_discharge')),
            ExportColumn::make('allow_partial_shipment')
                ->label(__('resources/proformaInvoice/strings.form.allow_partial_shipment'))
                ->state(fn(ProformaInvoice $record): string => $record->allow_partial_shipment ? 'Yes' : 'No'),
            ExportColumn::make('allow_trans_shipment')
                ->label(__('resources/proformaInvoice/strings.form.allow_trans_shipment'))
                ->state(fn(ProformaInvoice $record): string => $record->allow_trans_shipment ? 'Yes' : 'No'),

            ExportColumn::make('items')
                ->label(__('resources/proformaInvoice/strings.form.invoice_items'))
                ->state(function (ProformaInvoice $record): string {
                    return $record->items->map(function ($item) {
                        $product = $item->product?->getLocalizedNameAttribute() ?? 'N/A';
                        $quantity = $item->quantity;
                        $price = number_format($item->unit_price, 2);
                        $total = number_format($item->total_amount, 2);
                        $hsCode = $item->hs_code ? " (HS: {$item->hs_code})" : '';

                        return "- {$product}, Qty: {$quantity}, Price: {$price}, Total: {$total}{$hsCode}";
                    })->implode("\n");
                }),

            ExportColumn::make('creator.name')->label(__('resources/proformaInvoice/strings.table.creator')),
            ExportColumn::make('updater.name')->label(__('resources/proformaInvoice/strings.table.updater')),
            ExportColumn::make('created_at')->label(__('resources/proformaInvoice/strings.table.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/proformaInvoice/strings.table.updated_at')),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "ProformaInvoices-{$export->getKey()}";
    }
}
