<?php

namespace App\Filament\Resources\Operational\CustomResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Custom;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class CustomExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Custom::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('custom_no')->label('Custom No.'),
            ExportColumn::make('declaration_no')->label('Declaration No.'),
            ExportColumn::make('contract_no')->label('Contract No.'),

            ExportColumn::make('shipment.shipment_no')->label('Shipment No.'),
            ExportColumn::make('registeredOrder.ro_number')->label('Registered Order'),

            ExportColumn::make('clearance_type')->label('Clearance Type'),
            ExportColumn::make('commitment_balance')->label('Commitment Balance'),

            // Dates
            ExportColumn::make('clearance_date')->label('Clearance Date'),
            ExportColumn::make('doc_submission_date')->label('Doc Submission Date'),
            ExportColumn::make('ten_percent_exit_date')->label('10% Exit Date'),
            ExportColumn::make('rial_return_date')->label('Rial Return Date'),

            // Statuses (Double Columns)
            ExportColumn::make('clearanceStatus.name')->label('Clearance Status'),
            ExportColumn::make('clearanceStatus.english_name')->label('Clearance Status (EN)'),

            ExportColumn::make('bankGuaranteeStatus.name')->label('Bank Guarantee Status'),
            ExportColumn::make('bankGuaranteeStatus.english_name')->label('Bank Guarantee Status (EN)'),

            ExportColumn::make('commitmentStatus.name')->label('Commitment Status'),
            ExportColumn::make('commitmentStatus.english_name')->label('Commitment Status (EN)'),

            ExportColumn::make('notes')->label('Notes'),

            ExportColumn::make('creator.name')->label('Creator'),
            ExportColumn::make('updater.name')->label('Updater'),
            ExportColumn::make('created_at')->label('Created At'),
            ExportColumn::make('updated_at')->label('Updated At'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "CustomsClearance-{$export->getKey()}";
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'shipment',
            'registeredOrder',
            'clearanceStatus',
            'bankGuaranteeStatus',
            'commitmentStatus',
            'creator',
            'updater'
        ]);
    }
}
