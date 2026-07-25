<?php

namespace App\Filament\Resources\Operational\PaymentResource\Traits;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;

trait Infolist
{
    public static function viewAccountNo(): TextEntry
    {
        return TextEntry::make('account_no')
            ->label(__('resources/payment/strings.form.account_no'))
            ->icon('heroicon-m-identification')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/payment/strings.infolist.attachments'))
            ->schema([
                TextEntry::make('path')
                    ->hiddenLabel()
                    ->formatStateUsing(fn (string $state): string => basename($state))
                    ->tooltip(fn ($record) => $record->name ?? '')
                    ->icon('heroicon-m-paper-clip')
                    ->color('primary')
                    ->url(fn ($record): string => Storage::disk('public')->url($record->path), shouldOpenInNewTab: true),
            ])
            ->columns(1);
    }

    public static function viewBank(): TextEntry
    {
        return TextEntry::make('bank.name')
            ->label(__('resources/payment/strings.form.bank_name'))
            ->formatStateUsing(fn ($record): ?string => $record->bank?->getLocalizedNameAttribute())
            ->icon('heroicon-m-building-library')
            ->placeholder('-');
    }

    public static function viewBankAddress(): TextEntry
    {
        return TextEntry::make('bank_address')
            ->label(__('resources/payment/strings.form.bank_address'))
            ->icon('heroicon-m-map-pin')
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewBankCharges(): TextEntry
    {
        return TextEntry::make('bank_charges')
            ->label(__('resources/payment/strings.form.bank_charges'))
            ->color('success')
            ->formatStateUsing(fn ($state) => $state ? preciseNumber($state) : '-')
            ->placeholder('-');
    }

    public static function viewBeneficiaryAddress(): TextEntry
    {
        return TextEntry::make('beneficiary_address')
            ->label(__('resources/payment/strings.form.beneficiary_address'))
            ->icon('heroicon-m-map-pin')
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewBeneficiaryName(): TextEntry
    {
        return TextEntry::make('beneficiary_name')
            ->label(__('resources/payment/strings.form.beneficiary_name'))
            ->icon('heroicon-m-user')
            ->placeholder('-');
    }

    public static function viewCalculatedTotal(): TextEntry
    {
        return TextEntry::make('calculated_total')
            ->label(__('resources/payment/strings.form.summary_calculated_total'))
            ->icon('heroicon-m-calculator')
            ->color('success')
            ->formatStateUsing(fn ($state) => $state ? preciseNumber($state) : '-')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/payment/strings.infolist.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->icon('heroicon-m-calendar')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/payment/strings.infolist.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewCurrency(): TextEntry
    {
        return TextEntry::make('currency.name')
            ->label(__('resources/payment/strings.form.currency'))
            ->formatStateUsing(fn ($record): ?string => $record->currency?->getLocalizedNameAttribute())
            ->icon('heroicon-m-banknotes')
            ->placeholder('-');
    }

    public static function viewExchangeRate(): TextEntry
    {
        return TextEntry::make('exchange_rate')
            ->label(__('resources/payment/strings.form.exchange_rate'))
            ->formatStateUsing(fn ($state) => $state !== null ? preciseNumber($state) : '-')
            ->icon('heroicon-m-arrow-path-rounded-square')
            ->placeholder('-');
    }

    public static function viewIban(): TextEntry
    {
        return TextEntry::make('iban')
            ->label(__('resources/payment/strings.form.iban'))
            ->icon('heroicon-m-identification')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewNotes(): TextEntry
    {
        return TextEntry::make('notes')
            ->label(__('resources/payment/strings.form.notes'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewPayableAmount(): TextEntry
    {
        return TextEntry::make('payable_amount')
            ->label(__('resources/payment/strings.form.payable_amount'))
            ->color('success')
            ->formatStateUsing(fn ($state) => $state ? preciseNumber($state) : '-')
            ->placeholder('-');
    }

    public static function viewPayee(): TextEntry
    {
        return TextEntry::make('payee.name')
            ->label(__('resources/payment/strings.form.payee'))
            ->formatStateUsing(fn ($record): ?string => $record->payee?->getLocalizedNameAttribute())
            ->icon('heroicon-m-user')
            ->placeholder('-');
    }

    public static function viewPaymentDate(): TextEntry
    {
        return TextEntry::make('payment_date')
            ->label(__('resources/payment/strings.form.payment_date'))
            ->date()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewPaymentDeadline(): TextEntry
    {
        return TextEntry::make('payment_deadline')
            ->label(__('resources/payment/strings.form.payment_deadline'))
            ->date()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewPaymentNo(): TextEntry
    {
        return TextEntry::make('payment_no')
            ->label(__('resources/payment/strings.form.payment_no'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-hashtag')
            ->copyable();
    }

    public static function viewPayor(): TextEntry
    {
        return TextEntry::make('payor.name')
            ->label(__('resources/payment/strings.form.payor'))
            ->formatStateUsing(fn ($record): ?string => $record->payor?->getLocalizedNameAttribute())
            ->icon('heroicon-m-building-office')
            ->placeholder('-');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status.name')
            ->label(__('resources/payment/strings.form.status'))
            ->badge()
            ->formatStateUsing(fn ($record): ?string => $record->status?->getLocalizedNameAttribute())
            ->placeholder('-');
    }

    public static function viewSwift(): TextEntry
    {
        return TextEntry::make('swift')
            ->label(__('resources/payment/strings.form.swift'))
            ->icon('heroicon-m-globe-alt')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewTargetables(): TextEntry
    {
        return TextEntry::make('targetable.formatted_name')
            ->label(__('resources/payment/strings.form.targetable'))
            ->listWithLineBreaks()
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->icon('heroicon-m-clipboard-document-list');
    }

    public static function viewTotalAmount(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/payment/strings.form.total_amount_entered'))
            ->color('success')
            ->formatStateUsing(fn ($state) => $state ? preciseNumber($state) : '-')
            ->placeholder('-');
    }

    public static function viewTotalRatio(): TextEntry
    {
        return TextEntry::make('total_ratio')
            ->label(__('resources/payment/strings.form.summary_total_ratio'))
            ->formatStateUsing(fn ($state) => $state !== null ? preciseNumber($state * 100).'%' : '-')
            ->icon('heroicon-m-percent-badge')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/payment/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->icon('heroicon-m-clock')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/payment/strings.infolist.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }
}
