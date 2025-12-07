<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Shipment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class ShipmentExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Shipment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('shipment_no')->label('Shipment No.'),
            ExportColumn::make('registeredOrder.ro_number')->label('Registered Order'),
            ExportColumn::make('contract_no')->label('Contract No.'),
            ExportColumn::make('part')->label('Part'),
            ExportColumn::make('carrier.name')->label('Carrier'),
            ExportColumn::make('carrier.english_name')->label('Carrier E'),

            ExportColumn::make('warehouse_date')->label('Warehouse Date'),
            ExportColumn::make('exit_date')->label('Exit Date'),
            ExportColumn::make('eta')->label('ETA'),
            ExportColumn::make('etd')->label('ETD'),
            ExportColumn::make('bl_number')->label('BL Number'),
            ExportColumn::make('booking_no')->label('Booking No.'),
            ExportColumn::make('container_no')->label('Container No.'),
            ExportColumn::make('container_type')->label('Container Type'),

            ExportColumn::make('remittance_amount')->label('Remittance Amount'),
            ExportColumn::make('customs_quantity')->label('Customs Quantity'),
            ExportColumn::make('shipped_quantity')->label('Shipped Quantity'),

            ExportColumn::make('status.name')->label('Status'),
            ExportColumn::make('status.english_name')->label('Status E'),
            ExportColumn::make('trackingStatus.name')->label('Shipment Status'),
            ExportColumn::make('trackingStatus.english_name')->label('Shipment Status E'),
            ExportColumn::make('operationStatus.name')->label('Operation Status'),
            ExportColumn::make('operationStatus.english_name')->label('Operation Status E'),
            ExportColumn::make('containerStatus.name')->label('Container Status'),
            ExportColumn::make('containerStatus.english_name')->label('Container Status E'),
            ExportColumn::make('docStatus.name')->label('Doc Status'),
            ExportColumn::make('docStatus.english_name')->label('Doc Status E'),
            ExportColumn::make('docs')
                ->label('Documents')
                ->state(function (Shipment $record): string {
                    $docs = $record->docs;
                    if (!is_array($docs)) return '';

                    return collect($docs)->map(function ($item) {
                        $name = $item['name'] ?? '';
                        $received = ($item['received'] ?? false) ? '✅' : '❌';
                        return "{$name}: {$received}";
                    })->implode(' | ');
                }),

            ExportColumn::make('notes')->label('Notes'),

            ExportColumn::make('creator.name')->label('Creator'),
            ExportColumn::make('updater.name')->label('Updater'),
            ExportColumn::make('created_at')->label('Created At'),
            ExportColumn::make('updated_at')->label('Updated At'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "ShipmentLogistics-{$export->getKey()}";
    }


    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'registeredOrder',
            'carrier',
            'status',
            'containerStatus',
            'operationStatus',
            'trackingStatus',
            'docStatus',
            'creator',
            'updater'
        ]);
    }
}
