<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\BankProfile;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class BankProfileExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = BankProfile::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('bp_number')->label('BP Number'),
            ExportColumn::make('registeredOrder.ro_number')->label('Registered Order'),
            ExportColumn::make('order_number')->label('Order Number'),
            ExportColumn::make('status.name')->label('Status'),
            ExportColumn::make('status.english_name')->label('Status (E)'),
            ExportColumn::make('company.name')->label('Company'),
            ExportColumn::make('company.english_name')->label('Company (E)'),
            ExportColumn::make('bank.name')->label('Bank (Agent)'),
            ExportColumn::make('bank.english_name')->label('Bank (E)'),

            ExportColumn::make('targetable')
                ->label('Product/Category')
                ->state(fn(BankProfile $record) => $record->getTargetableFormatted('export')),

            ExportColumn::make('supply_source')->label('Supply Source')
                ->formatStateUsing(fn(?string $state) => $state ? __('resources/bankProfile/strings.general.supply_sources.' . $state) : '-'),

            ExportColumn::make('requestedCurrency.name')->label('Requested Currency'),
            ExportColumn::make('requestedCurrency.english_name')->label('Requested Currency (E)'),
            ExportColumn::make('requested_amount')->label('Requested Amount'),

            ExportColumn::make('purchasedCurrency.name')->label('Purchased Currency'),
            ExportColumn::make('purchasedCurrency.english_name')->label('Purchased Currency (E)'),
            ExportColumn::make('purchased_equivalent')->label('Purchased Equivalent'),

            ExportColumn::make('documents_amount')->label('Documents Amount'),
            ExportColumn::make('commission_rate')->label('Commission Rate (%)'),
            ExportColumn::make('exchange_rate')->label('Exchange Rate'),
            ExportColumn::make('final_rate')->label('Final Rate'),
            ExportColumn::make('conversion_rate')->label('Conversion Rate'),
            ExportColumn::make('creation_date')->label('Creation Date'),
            ExportColumn::make('allocation_date')->label('Allocation Date'),
            ExportColumn::make('purchase_date')->label('Purchase Date'),
            ExportColumn::make('delivery_date')->label('Delivery Date'),
            ExportColumn::make('notes')->label('Notes'),

            ExportColumn::make('commission_amount_purchased')
                ->label('Commission Amount')
                ->state(fn(BankProfile $record) => $record->commission_amount_purchased),
            ExportColumn::make('commission_equivalent')
                ->label('Commission Equivalent')
                ->state(fn(BankProfile $record) => $record->commission_equivalent),
            ExportColumn::make('final_equivalent')
                ->label('Final Equivalent')
                ->state(fn(BankProfile $record) => $record->final_equivalent),
            ExportColumn::make('remaining_commitment')
                ->label('Remaining Commitment')
                ->state(fn(BankProfile $record) => $record->remaining_commitment),
            ExportColumn::make('total_rial_remittance')
                ->label('Total (Rial)')
                ->state(fn(BankProfile $record) => $record->total_rial_remittance),
            ExportColumn::make('total_purchased_remittance')
                ->label('Total Purchased Remittance')
                ->state(fn(BankProfile $record) => $record->total_purchased_remittance),
            ExportColumn::make('total_requested_remittance')
                ->label('Total Requested Remittance')
                ->state(fn(BankProfile $record) => $record->total_requested_remittance),

            ExportColumn::make('creator.name')->label('Creator'),
            ExportColumn::make('updater.name')->label('Updater'),
            ExportColumn::make('created_at')->label('Created At'),
            ExportColumn::make('updated_at')->label('Updated At'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "BankProfiles-{$export->getKey()}";
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['registeredOrder', 'status', 'company', 'bank', 'requestedCurrency', 'purchasedCurrency', 'targetable', 'creator', 'updater']);
    }
}
