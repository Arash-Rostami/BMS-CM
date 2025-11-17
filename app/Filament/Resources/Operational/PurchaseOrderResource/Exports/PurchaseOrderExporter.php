<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\PurchaseOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseOrderExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = PurchaseOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('po_number')->label('PO Number'),
            ExportColumn::make("sellerCompany.name")->label('Seller'),
            ExportColumn::make("sellerCompany.english_name")->label('Seller E'),
            ExportColumn::make("buyerCompany.name")->label('Buyer'),
            ExportColumn::make("buyerCompany.english_name")->label('Buyer E'),
            ExportColumn::make("status.name")->label('Status'),
            ExportColumn::make("status.english_name")->label('Status E'),
            ExportColumn::make('order_date')->label('Order Date'),
            ExportColumn::make('validity_date')->label('Validity Date'),
            ExportColumn::make('expected_delivery_date')->label('Expected Delivery Date'),
            ExportColumn::make("currency.name")->label('Currency'),
            ExportColumn::make("currency.english_name")->label('Currency E'),
            ExportColumn::make('total_amount')->label('Total Amount'),
            ExportColumn::make('items')
                ->label('Items')
                ->state(function (PurchaseOrder $record): string {
                    return $record->items->map(function ($item) {
                        $product = $item->product?->getLocalizedNameAttribute() ?? 'N/A';
                        $quantity = $item->quantity;
                        $unit = $item->unit;
                        $price = number_format($item->unit_price, 2);

                        return "Product: {$product}, Qty: {$quantity} {$unit}, Price: {$price}";
                    })->implode("\n");
                }),
            ExportColumn::make('user.name')->label('Creator'),
            ExportColumn::make('updater.name')->label('Updater'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "PurchaseOrders-{$export->getKey()}";
    }
}
