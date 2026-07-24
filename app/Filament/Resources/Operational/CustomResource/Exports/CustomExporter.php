<?php

namespace App\Filament\Resources\Operational\CustomResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Custom;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CustomExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Custom::class;

    protected static function eagerLoadRelations(): array
    {
        return ['shipment', 'registeredOrder', 'clearanceStatus', 'bankGuaranteeStatus', 'commitmentStatus'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/custom/strings.export.id')),
            ExportColumn::make('custom_no')->label(__('resources/custom/strings.export.custom_no')),
            ExportColumn::make('declaration_no')->label(__('resources/custom/strings.export.declaration_no')),
            ExportColumn::make('contract_no')->label(__('resources/custom/strings.export.contract_no')),

            ExportColumn::make('shipment.shipment_no')->label(__('resources/custom/strings.export.shipment_no')),
            ExportColumn::make('registeredOrder.ro_number')->label(__('resources/custom/strings.export.registered_order')),

            ExportColumn::make('clearance_type')->label(__('resources/custom/strings.export.clearance_type')),
            ExportColumn::make('commitment_balance')->label(__('resources/custom/strings.export.commitment_balance')),

            ExportColumn::make('clearance_date')->label(__('resources/custom/strings.export.clearance_date')),
            ExportColumn::make('doc_submission_date')->label(__('resources/custom/strings.export.doc_submission_date')),
            ExportColumn::make('ten_percent_exit_date')->label(__('resources/custom/strings.export.ten_percent_exit_date')),
            ExportColumn::make('rial_return_date')->label(__('resources/custom/strings.export.rial_return_date')),

            ExportColumn::make('clearanceStatus.name')->label(__('resources/custom/strings.export.clearance_status')),
            ExportColumn::make('clearanceStatus.english_name')->label(__('resources/custom/strings.export.clearance_status_english')),

            ExportColumn::make('bankGuaranteeStatus.name')->label(__('resources/custom/strings.export.bank_guarantee_status')),
            ExportColumn::make('bankGuaranteeStatus.english_name')->label(__('resources/custom/strings.export.bank_guarantee_status_english')),

            ExportColumn::make('commitmentStatus.name')->label(__('resources/custom/strings.export.commitment_status')),
            ExportColumn::make('commitmentStatus.english_name')->label(__('resources/custom/strings.export.commitment_status_english')),

            ExportColumn::make('notes')->label(__('resources/custom/strings.export.notes')),

            ExportColumn::make('creator.name')->label(__('resources/custom/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/custom/strings.export.updater')),
            ExportColumn::make('created_at')->label(__('resources/custom/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/custom/strings.export.updated_at')),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "CustomsClearance-{$export->getKey()}";
    }
}
