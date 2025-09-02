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
            ExportColumn::make('requester.name')->label('Requester'),
            ExportColumn::make("department.name")->label('Department'),
            ExportColumn::make("department.english_name")->label('Department E'),
            ExportColumn::make("costCenter.name")->label('Cost Center'),
            ExportColumn::make("costCenter.english_name")->label('Cost Center E'),
            ExportColumn::make('required_by_date')->label('Required By Date'),
            ExportColumn::make('total_estimated_cost')->label('Total Estimated Cost'),
            ExportColumn::make('urgency_level')->label('Urgency Level'),
            ExportColumn::make("status.name")->label('Status'),
            ExportColumn::make("status.english_name")->label('Status E'),
            ExportColumn::make('approver.name')->label('Approver'),
            ExportColumn::make('approval_date')->label('Approval Date'),
            ExportColumn::make('items')
                ->label('Items')
                ->state(function (PurchaseRequest $record): string {
                    return $record->items->map(function ($item) {
                        $product = $item->product?->getLocalizedNameAttribute() ?? 'N/A';
                        $quantity = $item->quantity;
                        $unit = __('resources/target/strings.metrics.' . $item->unit) ?? $item->unit;
                        $cost = number_format($item->estimated_cost, 2);
                        $status = $item->status?->getLocalizedNameAttribute() ?? 'N/A';
                        $notesContent = str_replace(["\r\n", "\r", "\n"], ' ', $item->notes ?? '');
                        $notes = $notesContent ? " - Notes: {$notesContent}" : '';

                        return "Product: {$product}, Qty: {$quantity} {$unit}, Cost: {$cost}, Status: {$status}{$notes}";
                    })->implode(" | ");
                }),
            ExportColumn::make('creator.name')->label('Creator'),
            ExportColumn::make('updater.name')->label('Updater'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "PurchaseRequests-{$export->getKey()}";
    }
}
