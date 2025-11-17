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
            ExportColumn::make('currency.name')->label('Currency'),
            ExportColumn::make('currency.english_name')->label('Currency (E)'),
            ExportColumn::make('requested_amount')->label('Requested Amount'),
            ExportColumn::make('purchased_equivalent')->label('Purchased Equivalent'),
            ExportColumn::make('documents_amount')->label('Documents Amount'),
            ExportColumn::make('commission_rate')->label('Commission Rate (%)'),
            ExportColumn::make('exchange_rate')->label('Exchange Rate'),
            ExportColumn::make('final_rate')->label('Final Rate'),
            ExportColumn::make('eur_equivalent_rate')->label('EUR Equivalent Rate'),
            ExportColumn::make('allocation_date')->label('Allocation Date'),
            ExportColumn::make('purchase_date')->label('Purchase Date'),
            ExportColumn::make('delivery_date')->label('Delivery Date'),
            ExportColumn::make('notes')->label('Notes'),

            ExportColumn::make('commission_amount_purchased')
                ->label('Commission Amount')
                ->state(fn(BankProfile $record) => $record->commission_amount_purchased),
            ExportColumn::make('commission_equivalent_eur')
                ->label('Commission (EUR)')
                ->state(fn(BankProfile $record) => $record->commission_equivalent_eur),
            ExportColumn::make('final_eur_equivalent')
                ->label('Final (EUR)')
                ->state(fn(BankProfile $record) => $record->final_eur_equivalent),
            ExportColumn::make('remaining_commitment')
                ->label('Remaining Commitment')
                ->state(fn(BankProfile $record) => $record->remaining_commitment),
            ExportColumn::make('total_rial_remittance')
                ->label('Total (Rial)')
                ->state(fn(BankProfile $record) => $record->total_rial_remittance),
            ExportColumn::make('total_usd_remittance')
                ->label('Total (USD)')
                ->state(fn(BankProfile $record) => $record->total_usd_remittance),
            ExportColumn::make('total_eur_remittance')
                ->label('Total (EUR)')
                ->state(fn(BankProfile $record) => $record->total_eur_remittance),

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
        return $query->with(['registeredOrder', 'status', 'company', 'bank', 'currency', 'targetable', 'creator', 'updater']);
    }
}
