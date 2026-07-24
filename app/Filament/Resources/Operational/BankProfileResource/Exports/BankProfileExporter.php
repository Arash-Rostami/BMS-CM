<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\BankProfile;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BankProfileExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = BankProfile::class;

    protected static function eagerLoadRelations(): array
    {
        return ['registeredOrder', 'status', 'company', 'bank', 'requestedCurrency', 'purchasedCurrency', 'targetable', 'creator', 'updater'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/bankProfile/strings.export.id')),
            ExportColumn::make('bp_number')->label(__('resources/bankProfile/strings.export.bp_number')),
            ExportColumn::make('registeredOrder.ro_number')->label(__('resources/bankProfile/strings.export.registered_order')),
            ExportColumn::make('order_number')->label(__('resources/bankProfile/strings.export.order_number')),
            ExportColumn::make('status.name')->label(__('resources/bankProfile/strings.export.status')),
            ExportColumn::make('status.english_name')->label(__('resources/bankProfile/strings.export.status_english')),
            ExportColumn::make('company.name')->label(__('resources/bankProfile/strings.export.company')),
            ExportColumn::make('company.english_name')->label(__('resources/bankProfile/strings.export.company_english')),
            ExportColumn::make('bank.name')->label(__('resources/bankProfile/strings.export.bank')),
            ExportColumn::make('bank.english_name')->label(__('resources/bankProfile/strings.export.bank_english')),

            ExportColumn::make('targetable')
                ->label(__('resources/bankProfile/strings.export.targetable'))
                ->state(fn (BankProfile $record) => $record->getTargetableFormatted('export')),

            ExportColumn::make('supply_source')->label(__('resources/bankProfile/strings.export.supply_source'))
                ->formatStateUsing(fn (?string $state) => $state ? __('resources/bankProfile/strings.general.supply_sources.'.$state) : '-'),

            ExportColumn::make('requestedCurrency.name')->label(__('resources/bankProfile/strings.export.requested_currency')),
            ExportColumn::make('requestedCurrency.english_name')->label(__('resources/bankProfile/strings.export.requested_currency_english')),
            ExportColumn::make('requested_amount')->label(__('resources/bankProfile/strings.export.requested_amount')),

            ExportColumn::make('purchasedCurrency.name')->label(__('resources/bankProfile/strings.export.purchased_currency')),
            ExportColumn::make('purchasedCurrency.english_name')->label(__('resources/bankProfile/strings.export.purchased_currency_english')),
            ExportColumn::make('purchased_equivalent')->label(__('resources/bankProfile/strings.export.purchased_equivalent')),

            ExportColumn::make('documents_amount')->label(__('resources/bankProfile/strings.export.documents_amount')),
            ExportColumn::make('commission_rate')->label(__('resources/bankProfile/strings.export.commission_rate')),
            ExportColumn::make('exchange_rate')->label(__('resources/bankProfile/strings.export.exchange_rate')),
            ExportColumn::make('final_rate')->label(__('resources/bankProfile/strings.export.final_rate')),
            ExportColumn::make('conversion_rate')->label(__('resources/bankProfile/strings.export.conversion_rate')),
            ExportColumn::make('creation_date')->label(__('resources/bankProfile/strings.export.creation_date')),
            ExportColumn::make('allocation_date')->label(__('resources/bankProfile/strings.export.allocation_date')),
            ExportColumn::make('purchase_date')->label(__('resources/bankProfile/strings.export.purchase_date')),
            ExportColumn::make('delivery_date')->label(__('resources/bankProfile/strings.export.delivery_date')),
            ExportColumn::make('payment_due_date')->label(__('resources/bankProfile/strings.export.payment_due_date')),
            ExportColumn::make('commitment_payment_date')->label(__('resources/bankProfile/strings.export.commitment_payment_date')),
            ExportColumn::make('notes')->label(__('resources/bankProfile/strings.export.notes')),

            ExportColumn::make('commission_amount_purchased')
                ->label(__('resources/bankProfile/strings.export.commission_amount'))
                ->state(fn (BankProfile $record) => $record->commission_amount_purchased),
            ExportColumn::make('commission_equivalent')
                ->label(__('resources/bankProfile/strings.export.commission_equivalent'))
                ->state(fn (BankProfile $record) => $record->commission_equivalent),
            ExportColumn::make('final_equivalent')
                ->label(__('resources/bankProfile/strings.export.final_equivalent'))
                ->state(fn (BankProfile $record) => $record->final_equivalent),
            ExportColumn::make('remaining_commitment')
                ->label(__('resources/bankProfile/strings.export.remaining_commitment'))
                ->state(fn (BankProfile $record) => $record->remaining_commitment),
            ExportColumn::make('total_rial_remittance')
                ->label(__('resources/bankProfile/strings.export.total_rial'))
                ->state(fn (BankProfile $record) => $record->total_rial_remittance),
            ExportColumn::make('total_purchased_remittance')
                ->label(__('resources/bankProfile/strings.export.total_purchased_remittance'))
                ->state(fn (BankProfile $record) => $record->total_purchased_remittance),
            ExportColumn::make('total_requested_remittance')
                ->label(__('resources/bankProfile/strings.export.total_requested_remittance'))
                ->state(fn (BankProfile $record) => $record->total_requested_remittance),

            ExportColumn::make('creator.name')->label(__('resources/bankProfile/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/bankProfile/strings.export.updater')),
            ExportColumn::make('created_at')->label(__('resources/bankProfile/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/bankProfile/strings.export.updated_at')),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "BankProfiles-{$export->getKey()}";
    }
}
