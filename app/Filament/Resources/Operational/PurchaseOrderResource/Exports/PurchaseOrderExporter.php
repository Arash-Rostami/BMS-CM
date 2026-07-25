<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\PurchaseOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class PurchaseOrderExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = PurchaseOrder::class;

    protected static function eagerLoadRelations(): array
    {
        return ['sellerCompany', 'buyerCompany', 'status', 'currency', 'items.product'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/purchaseOrder/strings.export.id')),
            ExportColumn::make('po_number')->label(__('resources/purchaseOrder/strings.export.po_number')),
            ExportColumn::make('sellerCompany.name')->label(__('resources/purchaseOrder/strings.export.seller')),
            ExportColumn::make('sellerCompany.english_name')->label(__('resources/purchaseOrder/strings.export.seller_english')),
            ExportColumn::make('buyerCompany.name')->label(__('resources/purchaseOrder/strings.export.buyer')),
            ExportColumn::make('buyerCompany.english_name')->label(__('resources/purchaseOrder/strings.export.buyer_english')),
            ExportColumn::make('status.name')->label(__('resources/purchaseOrder/strings.export.status')),
            ExportColumn::make('status.english_name')->label(__('resources/purchaseOrder/strings.export.status_english')),
            ExportColumn::make('order_date')->label(__('resources/purchaseOrder/strings.export.order_date')),
            ExportColumn::make('validity_date')->label(__('resources/purchaseOrder/strings.export.validity_date')),
            ExportColumn::make('expected_delivery_date')->label(__('resources/purchaseOrder/strings.export.expected_delivery_date')),
            ExportColumn::make('currency.name')->label(__('resources/purchaseOrder/strings.export.currency')),
            ExportColumn::make('currency.english_name')->label(__('resources/purchaseOrder/strings.export.currency_english')),
            ExportColumn::make('total_amount')->label(__('resources/purchaseOrder/strings.export.total_amount'))
                ->formatStateUsing(fn ($state) => preciseNumber($state)),
            ExportColumn::make('items')
                ->label(__('resources/purchaseOrder/strings.export.items'))
                ->state(function (PurchaseOrder $record): string {
                    return $record->items->map(function ($item) {
                        $product = $item->product?->getLocalizedNameAttribute() ?? 'N/A';
                        $quantity = preciseNumber($item->quantity);
                        $unit = $item->unit;
                        $price = preciseNumber($item->unit_price);

                        return "Product: {$product}, Qty: {$quantity} {$unit}, Price: {$price}";
                    })->implode("\n");
                }),
            ExportColumn::make('creator.name')->label(__('resources/purchaseOrder/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/purchaseOrder/strings.export.updater')),
            ExportColumn::make('created_at')->label(__('resources/purchaseOrder/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/purchaseOrder/strings.export.updated_at')),
        ];
    }
}
