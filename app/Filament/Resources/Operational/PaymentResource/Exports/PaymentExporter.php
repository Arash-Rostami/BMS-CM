<?php

namespace App\Filament\Resources\Operational\PaymentResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class PaymentExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Payment::class;

    protected static function eagerLoadRelations(): array
    {
        return ['status', 'payor', 'payee', 'currency', 'bank', 'targetable'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/payment/strings.export.id')),
            ExportColumn::make('payment_no')->label(__('resources/payment/strings.export.payment_no')),
            ExportColumn::make('payment_date')->label(__('resources/payment/strings.export.payment_date')),
            ExportColumn::make('payment_deadline')->label(__('resources/payment/strings.export.payment_deadline')),

            ExportColumn::make('status.name')->label(__('resources/payment/strings.export.status')),
            ExportColumn::make('status.english_name')->label(__('resources/payment/strings.export.status_english')),

            ExportColumn::make('targetable')
                ->label(__('resources/payment/strings.export.targetable'))
                ->state(fn (Payment $record) => $record->getTargetableDisplay()),

            ExportColumn::make('payor.name')->label(__('resources/payment/strings.export.payor')),
            ExportColumn::make('payor.english_name')->label(__('resources/payment/strings.export.payor_english')),
            ExportColumn::make('payee.name')->label(__('resources/payment/strings.export.payee')),
            ExportColumn::make('payee.english_name')->label(__('resources/payment/strings.export.payee_english')),
            ExportColumn::make('bank.name')->label(__('resources/payment/strings.export.bank')),
            ExportColumn::make('bank.english_name')->label(__('resources/payment/strings.export.bank_english')),

            ExportColumn::make('beneficiary_name')->label(__('resources/payment/strings.export.beneficiary_name')),
            ExportColumn::make('beneficiary_address')->label(__('resources/payment/strings.export.beneficiary_address')),
            ExportColumn::make('bank_address')->label(__('resources/payment/strings.export.bank_address')),
            ExportColumn::make('account_no')->label(__('resources/payment/strings.export.account_no')),
            ExportColumn::make('swift')->label(__('resources/payment/strings.export.swift')),
            ExportColumn::make('iban')->label(__('resources/payment/strings.export.iban')),

            ExportColumn::make('currency.name')->label(__('resources/payment/strings.export.currency')),
            ExportColumn::make('currency.english_name')->label(__('resources/payment/strings.export.currency_english')),

            ExportColumn::make('payable_amount')->label(__('resources/payment/strings.export.payable_amount')),
            ExportColumn::make('bank_charges')->label(__('resources/payment/strings.export.bank_charges')),
            ExportColumn::make('total_amount')->label(__('resources/payment/strings.export.total_amount')),
            ExportColumn::make('exchange_rate')->label(__('resources/payment/strings.export.exchange_rate')),

            ExportColumn::make('calculated_total')
                ->label(__('resources/payment/strings.export.calculated_total'))
                ->state(fn (Payment $record) => ($record->payable_amount ?? 0) + ($record->bank_charges ?? 0)),
            ExportColumn::make('total_ratio')
                ->label(__('resources/payment/strings.export.total_ratio'))
                ->state(fn (Payment $record) => $record->total_amount > 0
                    ? round((($record->payable_amount ?? 0) + ($record->bank_charges ?? 0)) / $record->total_amount, 4)
                    : 0),

            ExportColumn::make('notes')->label(__('resources/payment/strings.export.notes')),

            ExportColumn::make('creator.name')->label(__('resources/payment/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/payment/strings.export.updater')),

            ExportColumn::make('created_at')->label(__('resources/payment/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/payment/strings.export.updated_at')),
        ];
    }
}
