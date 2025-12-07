<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\RegisteredOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RegisteredOrderExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = RegisteredOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('ro_number')->label('RO Number'),
            ExportColumn::make('contract_no')->label('Contract Number'),
            ExportColumn::make('official_registration_no')->label('Official Registration No'),
            ExportColumn::make('sellerCompany.name')->label('Seller'),
            ExportColumn::make('sellerCompany.english_name')->label('Seller E'),
            ExportColumn::make('buyerCompany.name')->label('Buyer'),
            ExportColumn::make('buyerCompany.english_name')->label('Buyer E'),
            ExportColumn::make('status.name')->label('Status'),
            ExportColumn::make('status.english_name')->label('Status E'),
            ExportColumn::make('order_date')->label('Order Date'),
            ExportColumn::make('validity_date')->label('Validity Date'),
            ExportColumn::make('expected_delivery_date')->label('Expected Delivery Date'),
            ExportColumn::make('incoterms')->label('Incoterms'),
            ExportColumn::make('currency.name')->label('Currency'),
            ExportColumn::make('currency.english_name')->label('Currency E'),
            ExportColumn::make('currency_type')->label('Currency Type'),
            ExportColumn::make('insurance_number')->label('Insurance Number'),
            ExportColumn::make('insurance_provider')->label('Insurance Provider'),
            ExportColumn::make('insurance_date')->label('Insurance Date'),
            ExportColumn::make('notes')->label('Notes'),
            ExportColumn::make('items')
                ->label('Line Items')
                ->state(function (RegisteredOrder $record): string {
                    return $record->items->map(function ($item) {
                        $product = $item->product?->getLocalizedNameAttribute() ?? 'N/A';
                        $quantity = $item->quantity ?? 0;
                        $unit = $item->unit ?? '';
                        $unitPrice = is_numeric($item->unit_price) ? number_format($item->unit_price, 2) : '0.00';
                        $netWeight = $item->net_weight ?? '';
                        $grossWeight = $item->gross_weight ?? '';
                        $entrance = is_numeric($item->entrance_fee) ? number_format($item->entrance_fee, 2) : '0.00';
                        $shipping = is_numeric($item->shipping_cost) ? number_format($item->shipping_cost, 2) : '0.00';
                        $extra = is_numeric($item->extra_cost) ? number_format($item->extra_cost, 2) : '0.00';
                        $line = is_numeric($item->line_total) ? number_format($item->line_total, 2) : '0.00';
                        $packing = $item->packing_details ? str_replace(["\r\n", "\n"], ' ', $item->packing_details) : '';
                        $desc = $item->description ? str_replace(["\r\n", "\n"], ' ', $item->description) : '';

                        return implode(' | ', array_filter([
                            "Product: {$product}",
                            "Qty: {$quantity} {$unit}",
                            "UnitPrice: {$unitPrice}",
                            $netWeight !== '' ? "NetWt: {$netWeight}" : null,
                            $grossWeight !== '' ? "GrossWt: {$grossWeight}" : null,
                            "Entrance: {$entrance}",
                            "Shipping: {$shipping}",
                            "Extra: {$extra}",
                            "LineTotal: {$line}",
                            $packing ? "Packing: {$packing}" : null,
                            $desc ? "Desc: {$desc}" : null,
                        ]));
                    })->implode("\n");
                }),
            ExportColumn::make('user.name')->label('Creator'),
            ExportColumn::make('updater.name')->label('Updater'),
            ExportColumn::make('created_at')->label('Created At'),
            ExportColumn::make('updated_at')->label('Updated At'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "RegisteredOrders-{$export->getKey()}";
    }
}
