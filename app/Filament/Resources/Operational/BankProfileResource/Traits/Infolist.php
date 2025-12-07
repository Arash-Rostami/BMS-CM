<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use App\Models\Product;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

trait Infolist
{
    public static function viewAllocationDate(): TextEntry
    {
        return TextEntry::make('allocation_date')
            ->label(__('resources/bankProfile/strings.form.allocation_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/bankProfile/strings.infolist.attachments'))
            ->schema([
                TextEntry::make('path')
                    ->hiddenLabel()
                    ->formatStateUsing(fn(string $state): string => basename($state))
                    ->tooltip(fn($record) => $record->name ?? '')
                    ->icon('heroicon-m-paper-clip')
                    ->color('primary')
                    ->url(fn($record): string => Storage::disk('public')->url($record->path), shouldOpenInNewTab: true),
            ])
            ->columns(1);
    }

    public static function viewBank(): TextEntry
    {
        return TextEntry::make('bank.name')
            ->label(__('resources/bankProfile/strings.form.bank'))
            ->formatStateUsing(fn($record): ?string => $record->bank?->getLocalizedNameAttribute())
            ->icon('heroicon-m-building-library')
            ->placeholder('-');
    }

    public static function viewBpNumber(): TextEntry
    {
        return TextEntry::make('bp_number')
            ->label(__('resources/bankProfile/strings.form.bp_number'))
            ->icon('heroicon-m-hashtag')
            ->copyable();
    }

    public static function viewCommissionAmountPurchased(): TextEntry
    {
        return TextEntry::make('commission_amount_purchased')
            ->label(__('resources/bankProfile/strings.form.summary_commission_amount'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewCommissionEquivalent(): TextEntry
    {
        return TextEntry::make('commission_equivalent')
            ->label(__('resources/bankProfile/strings.form.summary_commission_equivalent'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewCommissionRate(): TextEntry
    {
        return TextEntry::make('commission_rate')
            ->label(__('resources/bankProfile/strings.form.commission_rate'))
            ->formatStateUsing(fn($state) => $state !== null ? number_format($state, 2) . '%' : '-')
            ->icon('heroicon-m-percent-badge')
            ->placeholder('-');
    }

    public static function viewCompany(): TextEntry
    {
        return TextEntry::make('company.name')
            ->label(__('resources/bankProfile/strings.form.company'))
            ->formatStateUsing(fn($record): ?string => $record->company?->getLocalizedNameAttribute())
            ->icon('heroicon-m-building-office')
            ->placeholder('-');
    }

    public static function viewConversionRate(): TextEntry
    {
        return TextEntry::make('conversion_rate')
            ->label(__('resources/bankProfile/strings.form.conversion_rate'))
            ->formatStateUsing(fn($state) => $state !== null ? number_format($state, 5) : '-')
            ->icon('heroicon-m-calculator')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/bankProfile/strings.infolist.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreationDate(): TextEntry
    {
        return TextEntry::make('creation_date')
            ->label(__('resources/bankProfile/strings.form.creation_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/bankProfile/strings.infolist.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewCurrency(): TextEntry
    {
        return TextEntry::make('currency.name')
            ->label(__('resources/bankProfile/strings.form.requested_currency'))
            ->formatStateUsing(fn($record): ?string => $record->currency?->getLocalizedNameAttribute())
            ->icon('heroicon-m-banknotes')
            ->placeholder('-');
    }

    public static function viewDeliveryDate(): TextEntry
    {
        return TextEntry::make('delivery_date')
            ->label(__('resources/bankProfile/strings.form.delivery_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewDocumentsAmount(): TextEntry
    {
        return TextEntry::make('documents_amount')
            ->label(__('resources/bankProfile/strings.form.documents_amount'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewEurEquivalentRate(): TextEntry
    {
        return TextEntry::make('eur_equivalent_rate')
            ->label(__('resources/bankProfile/strings.form.eur_equivalent_rate'))
            ->formatStateUsing(fn($state) => $state !== null ? number_format($state, 5) : '-')
            ->icon('heroicon-m-calculator')
            ->placeholder('-');
    }

    public static function viewExchangeRate(): TextEntry
    {
        return TextEntry::make('exchange_rate')
            ->label(__('resources/bankProfile/strings.form.exchange_rate'))
            ->formatStateUsing(fn($state) => $state !== null ? number_format($state, 5) : '-')
            ->icon('heroicon-m-calculator')
            ->placeholder('-');
    }

    public static function viewFinalEquivalent(): TextEntry
    {
        return TextEntry::make('final_equivalent')
            ->label(__('resources/bankProfile/strings.form.summary_final_equivalent'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewFinalEurEquivalent(): TextEntry
    {
        return TextEntry::make('final_eur_equivalent')
            ->label(__('resources/bankProfile/strings.form.summary_final_eur'))
            ->money('EUR')
            ->icon('heroicon-m-currency-euro')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewFinalRate(): TextEntry
    {
        return TextEntry::make('final_rate')
            ->label(__('resources/bankProfile/strings.form.final_rate'))
            ->formatStateUsing(fn($state) => $state !== null ? number_format($state, 5) : '-')
            ->icon('heroicon-m-calculator')
            ->placeholder('-');
    }

    public static function viewNotes(): TextEntry
    {
        return TextEntry::make('notes')
            ->label(__('resources/bankProfile/strings.form.notes'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewOrderNumber(): TextEntry
    {
        return TextEntry::make('order_number')
            ->label(__('resources/bankProfile/strings.form.order_number'))
            ->copyable()
            ->icon('heroicon-m-hashtag')
            ->placeholder('-');
    }

    public static function viewPurchaseDate(): TextEntry
    {
        return TextEntry::make('purchase_date')
            ->label(__('resources/bankProfile/strings.form.purchase_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewPurchasedCurrency(): TextEntry
    {
        return TextEntry::make('purchasedCurrency.name')
            ->label(__('resources/bankProfile/strings.form.purchased_currency'))
            ->formatStateUsing(fn($record): ?string => $record->purchasedCurrency?->getLocalizedNameAttribute())
            ->icon('heroicon-m-banknotes')
            ->placeholder('-');
    }

    public static function viewPurchasedEquivalent(): TextEntry
    {
        return TextEntry::make('purchased_equivalent')
            ->label(__('resources/bankProfile/strings.form.purchased_equivalent'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewRegisteredOrder(): TextEntry
    {
        return TextEntry::make('registeredOrder.formatted_name')
            ->label(__('resources/bankProfile/strings.form.registered_order'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->icon('heroicon-m-clipboard-document-list')
            ->copyable();
    }

    public static function viewRemainingCommitment(): TextEntry
    {
        return TextEntry::make('remaining_commitment')
            ->label(__('resources/bankProfile/strings.form.summary_remaining'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewRequestedAmount(): TextEntry
    {
        return TextEntry::make('requested_amount')
            ->label(__('resources/bankProfile/strings.form.requested_amount'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewRequestedCurrency(): TextEntry
    {
        return TextEntry::make('requestedCurrency.name')
            ->label(__('resources/bankProfile/strings.form.requested_currency'))
            ->formatStateUsing(fn($record): ?string => $record->requestedCurrency?->getLocalizedNameAttribute())
            ->icon('heroicon-m-banknotes')
            ->placeholder('-');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status.name')
            ->label(__('resources/bankProfile/strings.form.status'))
            ->badge()
            ->formatStateUsing(fn($record): ?string => $record->status?->getLocalizedNameAttribute())
            ->placeholder('-');
    }

    public static function viewSupplySource(): TextEntry
    {
        return TextEntry::make('supply_source')
            ->label(__('resources/bankProfile/strings.form.supply_source'))
            ->badge()
            ->color('info')
            ->formatStateUsing(fn($state): ?string => $state ? __('resources/bankProfile/strings.general.supply_sources.' . $state) : '-')
            ->placeholder('-');
    }

    public static function viewTargetable(): TextEntry
    {
        return TextEntry::make('targetable.name')
            ->label(__('resources/bankProfile/strings.form.targetable'))
            ->formatStateUsing(function (Model $record) {
                if (!$record->targetable) {
                    return '-';
                }
                return $record->targetable_type === Product::class
                    ? $record->targetable?->customized_label ?? $record->targetable?->getLocalizedNameAttribute()
                    : $record->targetable?->getLocalizedNameAttribute();
            })
            ->badge()
            ->color(fn($record) => $record->targetable_type === Product::class ? 'success' : 'warning')
            ->placeholder('-');
    }

    public static function viewTotalEurRemittance(): TextEntry
    {
        return TextEntry::make('total_eur_remittance')
            ->label(__('resources/bankProfile/strings.form.summary_total_eur'))
            ->money('EUR')
            ->icon('heroicon-m-currency-euro')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewTotalPurchasedRemittance(): TextEntry
    {
        return TextEntry::make('total_purchased_remittance')
            ->label(__('resources/bankProfile/strings.form.summary_total_purchased'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewTotalRequestedRemittance(): TextEntry
    {
        return TextEntry::make('total_requested_remittance')
            ->label(__('resources/bankProfile/strings.form.summary_total_requested'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewTotalRialRemittance(): TextEntry
    {
        return TextEntry::make('total_rial_remittance')
            ->label(__('resources/bankProfile/strings.form.summary_total_rial'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewTotalUsdRemittance(): TextEntry
    {
        return TextEntry::make('total_usd_remittance')
            ->label(__('resources/bankProfile/strings.form.summary_total_usd'))
            ->money('USD')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/bankProfile/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/bankProfile/strings.infolist.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }
}
