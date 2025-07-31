<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\PurchaseRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseRequestExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = PurchaseRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('requester.name'),
            ExportColumn::make('department.name'),
            ExportColumn::make('costCenter.name'),
            ExportColumn::make('required_by_date'),
            ExportColumn::make('total_estimated_cost'),
            ExportColumn::make('urgency_level'),
            ExportColumn::make('status.name'),
            ExportColumn::make('approver.name'),
            ExportColumn::make('approval_date'),
            ExportColumn::make('items')
                ->label('Purchase Items')
                ->state(function (PurchaseRequest $record): string {
                    return $record->items->map(function ($item) {
                        $product = $item->product?->getLocalizedNameAttribute() ?? 'N/A';
                        $quantity = $item->quantity;
                        $unit = __('resources/target/strings.metrics.' . $item->unit) ?? $item->unit;
                        $cost =  number_format($item->estimated_cost, 2);
                        $status = $item->status?->getLocalizedNameAttribute() ?? 'N/A';
                        $notes = $item->notes ? " - Notes: {$item->notes}" : '';

                        return "Product: {$product}, Qty: {$quantity} {$unit}, Cost: {$cost}, Status: {$status}{$notes}";
                    })->implode("\n");
                }),
            ExportColumn::make('creator.name'),
            ExportColumn::make('updater.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "PurchaseRequests-{$export->getKey()}";
    }
}
