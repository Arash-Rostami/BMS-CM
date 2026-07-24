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

    protected static function eagerLoadRelations(): array
    {
        return ['sellerCompany', 'buyerCompany', 'status', 'currency', 'items.product'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/registeredOrder/strings.export.id')),
            ExportColumn::make('ro_number')->label(__('resources/registeredOrder/strings.export.ro_number')),
            ExportColumn::make('contract_no')->label(__('resources/registeredOrder/strings.export.contract_no')),
            ExportColumn::make('official_registration_no')->label(__('resources/registeredOrder/strings.export.official_registration_no')),
            ExportColumn::make('sellerCompany.name')->label(__('resources/registeredOrder/strings.export.seller')),
            ExportColumn::make('sellerCompany.english_name')->label(__('resources/registeredOrder/strings.export.seller_english')),
            ExportColumn::make('buyerCompany.name')->label(__('resources/registeredOrder/strings.export.buyer')),
            ExportColumn::make('buyerCompany.english_name')->label(__('resources/registeredOrder/strings.export.buyer_english')),
            ExportColumn::make('status.name')->label(__('resources/registeredOrder/strings.export.status')),
            ExportColumn::make('status.english_name')->label(__('resources/registeredOrder/strings.export.status_english')),
            ExportColumn::make('order_date')->label(__('resources/registeredOrder/strings.export.order_date')),
            ExportColumn::make('validity_date')->label(__('resources/registeredOrder/strings.export.validity_date')),
            ExportColumn::make('expected_delivery_date')->label(__('resources/registeredOrder/strings.export.expected_delivery_date')),
            ExportColumn::make('incoterms')->label(__('resources/registeredOrder/strings.export.incoterms')),
            ExportColumn::make('currency.name')->label(__('resources/registeredOrder/strings.export.currency')),
            ExportColumn::make('currency.english_name')->label(__('resources/registeredOrder/strings.export.currency_english')),
            ExportColumn::make('currency_type')->label(__('resources/registeredOrder/strings.export.currency_type')),
            ExportColumn::make('insurance_number')->label(__('resources/registeredOrder/strings.export.insurance_number')),
            ExportColumn::make('insurance_provider')->label(__('resources/registeredOrder/strings.export.insurance_provider')),
            ExportColumn::make('insurance_date')->label(__('resources/registeredOrder/strings.export.insurance_date')),
            ExportColumn::make('notes')->label(__('resources/registeredOrder/strings.export.notes')),
            ExportColumn::make('items')
                ->label(__('resources/registeredOrder/strings.export.line_items'))
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
                            __('resources/registeredOrder/strings.export.item_product').": {$product}",
                            __('resources/registeredOrder/strings.export.item_quantity').": {$quantity} {$unit}",
                            __('resources/registeredOrder/strings.export.item_unit_price').": {$unitPrice}",
                            $netWeight !== '' ? __('resources/registeredOrder/strings.export.item_net_weight').": {$netWeight}" : null,
                            $grossWeight !== '' ? __('resources/registeredOrder/strings.export.item_gross_weight').": {$grossWeight}" : null,
                            __('resources/registeredOrder/strings.export.item_entrance_fee').": {$entrance}",
                            __('resources/registeredOrder/strings.export.item_shipping_cost').": {$shipping}",
                            __('resources/registeredOrder/strings.export.item_extra_cost').": {$extra}",
                            __('resources/registeredOrder/strings.export.item_line_total').": {$line}",
                            $packing ? __('resources/registeredOrder/strings.export.item_packing').": {$packing}" : null,
                            $desc ? __('resources/registeredOrder/strings.export.item_description').": {$desc}" : null,
                        ]));
                    })->implode("\n");
                }),
            ExportColumn::make('creator.name')->label(__('resources/registeredOrder/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/registeredOrder/strings.export.updater')),
            ExportColumn::make('created_at')->label(__('resources/registeredOrder/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/registeredOrder/strings.export.updated_at')),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "RegisteredOrders-{$export->getKey()}";
    }
}
