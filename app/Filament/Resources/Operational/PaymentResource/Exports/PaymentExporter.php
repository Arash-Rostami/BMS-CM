<?php

namespace App\Filament\Resources\Operational\PaymentResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class PaymentExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('payment_no')->label('Payment Number'),
            ExportColumn::make('payment_date')->label('Payment Date'),
            ExportColumn::make('payment_deadline')->label('Payment Deadline'),

            ExportColumn::make('status.name')->label('Status'),
            ExportColumn::make('status.english_name')->label('Status (E)'),

            ExportColumn::make('targetable')
                ->label('Related To')
                ->state(fn(Payment $record) => $record->getTargetableDisplay()),

            ExportColumn::make('payor.name')->label('Payor'),
            ExportColumn::make('payor.english_name')->label('Payor (E)'),
            ExportColumn::make('payee.name')->label('Payee'),
            ExportColumn::make('payee.english_name')->label('Payee (E)'),
            ExportColumn::make('bank.name')->label('Bank'),
            ExportColumn::make('bank.english_name')->label('Bank (E)'),

            ExportColumn::make('beneficiary_name')->label('Beneficiary Name'),
            ExportColumn::make('beneficiary_address')->label('Beneficiary Address'),
            ExportColumn::make('bank_address')->label('Bank Address'),
            ExportColumn::make('account_no')->label('Account Number'),
            ExportColumn::make('swift')->label('SWIFT'),
            ExportColumn::make('iban')->label('IBAN'),

            ExportColumn::make('currency.name')->label('Currency'),
            ExportColumn::make('currency.english_name')->label('Currency (E)'),

            ExportColumn::make('payable_amount')->label('Payable Amount'),
            ExportColumn::make('bank_charges')->label('Bank Charges'),
            ExportColumn::make('total_amount')->label('Total Amount'),
            ExportColumn::make('exchange_rate')->label('Exchange Rate'),

            ExportColumn::make('calculated_total')
                ->label('Calculated Total')
                ->state(fn(Payment $record) => ($record->payable_amount ?? 0) + ($record->bank_charges ?? 0)),
            ExportColumn::make('total_ratio')
                ->label('Ratio')
                ->state(fn(Payment $record) => $record->total_amount > 0
                    ? round((($record->payable_amount ?? 0) + ($record->bank_charges ?? 0)) / $record->total_amount, 4)
                    : 0),

            ExportColumn::make('notes')->label('Notes'),

            ExportColumn::make('creator.name')->label('Creator'),
            ExportColumn::make('updater.name')->label('Updater'),

            ExportColumn::make('created_at')->label('Created At'),
            ExportColumn::make('updated_at')->label('Updated At'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "Payments-{$export->getKey()}";
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['status', 'payor', 'payee', 'currency', 'bank', 'creator', 'updater', 'targetable']);
    }
}
