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
            ExportColumn::make('invoice_no')->label('Invoice Number'),
            ExportColumn::make('invoice_date')->label('Invoice Date'),
            ExportColumn::make('validity_date')->label('Validity Date'),
            ExportColumn::make("sellerCompany.name")->label('Seller'),
            ExportColumn::make("sellerCompany.english_name")->label('Seller E'),
            ExportColumn::make("consigneeCompany.name")->label('Buyer (Consignee)'),
            ExportColumn::make("consigneeCompany.english_name")->label('Buyer (Consignee) E'),
            ExportColumn::make('discount')->label('Discount'),
            ExportColumn::make('freight_charges')->label('Freight Charges'),
            ExportColumn::make('other_charges')->label('Other Charges'),
            ExportColumn::make('total_cfr_amount')->label('Total Amount'),
            ExportColumn::make("mainCurrency.name")->label('Main Currency'),
            ExportColumn::make("mainCurrency.english_name")->label('Main Currency E'),
            ExportColumn::make("secondaryCurrency.name")->label('Secondary Currency'),
            ExportColumn::make("secondaryCurrency.english_name")->label('Secondary Currency E'),
            ExportColumn::make('delivery_terms')->label('Delivery Terms'),
            ExportColumn::make('transport_mode')->label('Transport Mode'),
            ExportColumn::make('contract_no')->label('Contract No'),
            ExportColumn::make('buyer_comm_card_num')->label('Buyer Commercial Card Number'),
            ExportColumn::make('origin_country')->label('Country of Origin'),
            ExportColumn::make('destination_country')->label('Country of Destination'),
            ExportColumn::make('beneficiary_country')->label('Beneficiary Country'),
            ExportColumn::make('port_of_loading')->label('Port of Loading'),
            ExportColumn::make('port_of_discharge')->label('Port of Discharge'),
            ExportColumn::make('allow_partial_shipment')
                ->label('Allow Partial Shipment')
                ->state(fn(ProformaInvoice $record): string => $record->allow_partial_shipment ? 'Yes' : 'No'),
            ExportColumn::make('allow_trans_shipment')
                ->label('Allow Trans-shipment')
                ->state(fn(ProformaInvoice $record): string => $record->allow_trans_shipment ? 'Yes' : 'No'),

            ExportColumn::make('items')
                ->label('Items')
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

            ExportColumn::make('creator.name')->label('Creator'),
            ExportColumn::make('updater.name')->label('Updater'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "ProformaInvoices-{$export->getKey()}";
    }
}
