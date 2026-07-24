<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Shipment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class ShipmentExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Shipment::class;

    protected static function eagerLoadRelations(): array
    {
        return ['registeredOrder', 'carrier', 'status', 'containerStatus', 'operationStatus', 'trackingStatus', 'docStatus'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/shipment/strings.export.id')),
            ExportColumn::make('shipment_no')->label(__('resources/shipment/strings.export.shipment_no')),
            ExportColumn::make('registeredOrder.ro_number')->label(__('resources/shipment/strings.export.registered_order')),
            ExportColumn::make('contract_no')->label(__('resources/shipment/strings.export.contract_no')),
            ExportColumn::make('part')->label(__('resources/shipment/strings.export.part')),
            ExportColumn::make('carrier.name')->label(__('resources/shipment/strings.export.carrier')),
            ExportColumn::make('carrier.english_name')->label(__('resources/shipment/strings.export.carrier_english')),

            ExportColumn::make('warehouse_date')->label(__('resources/shipment/strings.export.warehouse_date')),
            ExportColumn::make('exit_date')->label(__('resources/shipment/strings.export.exit_date')),
            ExportColumn::make('eta')->label(__('resources/shipment/strings.export.eta')),
            ExportColumn::make('etd')->label(__('resources/shipment/strings.export.etd')),
            ExportColumn::make('bl_number')->label(__('resources/shipment/strings.export.bl_number')),
            ExportColumn::make('booking_no')->label(__('resources/shipment/strings.export.booking_no')),
            ExportColumn::make('container_no')->label(__('resources/shipment/strings.export.container_no')),
            ExportColumn::make('container_type')->label(__('resources/shipment/strings.export.container_type')),

            ExportColumn::make('remittance_amount')->label(__('resources/shipment/strings.export.remittance_amount')),
            ExportColumn::make('customs_quantity')->label(__('resources/shipment/strings.export.customs_quantity')),
            ExportColumn::make('shipped_quantity')->label(__('resources/shipment/strings.export.shipped_quantity')),

            ExportColumn::make('status.name')->label(__('resources/shipment/strings.export.status')),
            ExportColumn::make('status.english_name')->label(__('resources/shipment/strings.export.status_english')),
            ExportColumn::make('trackingStatus.name')->label(__('resources/shipment/strings.export.shipment_status')),
            ExportColumn::make('trackingStatus.english_name')->label(__('resources/shipment/strings.export.shipment_status_english')),
            ExportColumn::make('operationStatus.name')->label(__('resources/shipment/strings.export.operation_status')),
            ExportColumn::make('operationStatus.english_name')->label(__('resources/shipment/strings.export.operation_status_english')),
            ExportColumn::make('containerStatus.name')->label(__('resources/shipment/strings.export.container_status')),
            ExportColumn::make('containerStatus.english_name')->label(__('resources/shipment/strings.export.container_status_english')),
            ExportColumn::make('docStatus.name')->label(__('resources/shipment/strings.export.doc_status')),
            ExportColumn::make('docStatus.english_name')->label(__('resources/shipment/strings.export.doc_status_english')),
            ExportColumn::make('docs')
                ->label(__('resources/shipment/strings.export.documents'))
                ->state(function (Shipment $record): string {
                    $docs = $record->docs['items'] ?? [];
                    if (! is_array($docs) || $docs === []) {
                        return '';
                    }

                    return collect($docs)->map(function ($item) {
                        $name = $item['name'] ?? '';
                        $received = ($item['received'] ?? false) ? '✅' : '❌';

                        return "{$name}: {$received}";
                    })->implode(' | ');
                }),

            ExportColumn::make('notes')->label(__('resources/shipment/strings.export.notes')),

            ExportColumn::make('creator.name')->label(__('resources/shipment/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/shipment/strings.export.updater')),
            ExportColumn::make('created_at')->label(__('resources/shipment/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/shipment/strings.export.updated_at')),
        ];
    }
}
