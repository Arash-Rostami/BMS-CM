<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\PurchaseRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class PurchaseRequestExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = PurchaseRequest::class;

    protected static function eagerLoadRelations(): array
    {
        return ['requester', 'department', 'costCenter', 'status', 'approver', 'items.product', 'items.status'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/purchaseRequest/strings.export.id')),
            ExportColumn::make('requester.name')->label(__('resources/purchaseRequest/strings.export.requester')),
            ExportColumn::make('department.name')->label(__('resources/purchaseRequest/strings.export.department')),
            ExportColumn::make('department.english_name')->label(__('resources/purchaseRequest/strings.export.department_english')),
            ExportColumn::make('costCenter.name')->label(__('resources/purchaseRequest/strings.export.cost_center')),
            ExportColumn::make('costCenter.english_name')->label(__('resources/purchaseRequest/strings.export.cost_center_english')),
            ExportColumn::make('required_by_date')->label(__('resources/purchaseRequest/strings.export.required_by_date')),
            ExportColumn::make('total_estimated_cost')->label(__('resources/purchaseRequest/strings.export.total_estimated_cost'))
                ->formatStateUsing(fn ($state) => preciseNumber($state)),
            ExportColumn::make('urgency_level')->label(__('resources/purchaseRequest/strings.export.urgency_level')),
            ExportColumn::make('status.name')->label(__('resources/purchaseRequest/strings.export.status')),
            ExportColumn::make('status.english_name')->label(__('resources/purchaseRequest/strings.export.status_english')),
            ExportColumn::make('approver.name')->label(__('resources/purchaseRequest/strings.export.approver')),
            ExportColumn::make('approval_date')->label(__('resources/purchaseRequest/strings.export.approval_date')),
            ExportColumn::make('items')
                ->label(__('resources/purchaseRequest/strings.export.items'))
                ->state(function (PurchaseRequest $record): string {
                    return $record->items->map(function ($item) {
                        $product = $item->product?->getLocalizedNameAttribute() ?? 'N/A';
                        $quantity = preciseNumber($item->quantity);
                        $unit = __('resources/general/strings.metrics.'.$item->unit) ?? $item->unit;
                        $cost = preciseNumber($item->estimated_cost);
                        $status = $item->status?->getLocalizedNameAttribute() ?? 'N/A';
                        $notesContent = str_replace(["\r\n", "\r", "\n"], ' ', $item->notes ?? '');
                        $notes = $notesContent ? " - Notes: {$notesContent}" : '';

                        return "Product: {$product}, Qty: {$quantity} {$unit}, Cost: {$cost}, Status: {$status}{$notes}";
                    })->implode(' | ');
                }),
            ExportColumn::make('creator.name')->label(__('resources/purchaseRequest/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/purchaseRequest/strings.export.updater')),
            ExportColumn::make('created_at')->label(__('resources/purchaseRequest/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/purchaseRequest/strings.export.updated_at')),
        ];
    }
}
