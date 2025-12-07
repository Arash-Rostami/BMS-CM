<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\CommitmentStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\GuaranteeStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;

trait Infolist
{
    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/custom/strings.form.attachments'))
            ->schema([
                TextEntry::make('path')
                    ->hiddenLabel()
                    ->formatStateUsing(fn(string $state): string => basename($state))
                    ->tooltip(fn($record) => $record->name ?? '')
                    ->icon('heroicon-m-paper-clip')
                    ->color('primary')
                    ->url(fn($record) => Storage::disk('public')->url($record->path), shouldOpenInNewTab: true),
            ])
            ->columns(1);
    }

    public static function viewBankGuaranteeStatus(): TextEntry
    {
        return TextEntry::make('bankGuaranteeStatus.name')
            ->label(__('resources/custom/strings.form.bank_guarantee_status'))
            ->formatStateUsing(fn($record) => $record->bankGuaranteeStatus?->getLocalizedNameAttribute())
            ->badge()
            ->color(fn($record) => GuaranteeStatus::tryFrom($record->bankGuaranteeStatus?->english_name)?->getColor() ?? 'gray')
            ->placeholder('-');
    }

    public static function viewClearanceDate(): TextEntry
    {
        return TextEntry::make('clearance_date')
            ->label(__('resources/custom/strings.form.clearance_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewClearanceStatus(): TextEntry
    {
        return TextEntry::make('clearanceStatus.name')
            ->label(__('resources/custom/strings.form.clearance_status'))
            ->formatStateUsing(fn($record) => $record->clearanceStatus?->getLocalizedNameAttribute())
            ->badge()
            ->color(fn($record) => ClearanceStatus::tryFrom($record->clearanceStatus?->english_name)?->getColor() ?? 'gray')
            ->icon(fn($record) => ClearanceStatus::tryFrom($record->clearanceStatus?->english_name)?->getIcon())
            ->placeholder('-');
    }

    public static function viewClearanceType(): TextEntry
    {
        return TextEntry::make('clearance_type')
            ->label(__('resources/custom/strings.form.clearance_type'))
            ->formatStateUsing(fn($state) => $state ? ['90_percent' => '90%', '10_percent' => '10%'][$state] : '-')
            ->icon('heroicon-m-scale')
            ->placeholder('-');
    }

    public static function viewCommitmentBalance(): TextEntry
    {
        return TextEntry::make('commitment_balance')
            ->label(__('resources/custom/strings.form.commitment_balance'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewCommitmentPaymentDate(): TextEntry
    {
        return TextEntry::make('commitment_payment_date')
            ->label(__('resources/custom/strings.form.commitment_payment_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewCommitmentStatus(): TextEntry
    {
        return TextEntry::make('commitmentStatus.name')
            ->label(__('resources/custom/strings.form.commitment_status'))
            ->formatStateUsing(fn($record) => $record->commitmentStatus?->getLocalizedNameAttribute())
            ->badge()
            ->color(fn($record) => CommitmentStatus::tryFrom($record->commitmentStatus?->english_name)?->getColor() ?? 'gray')
            ->placeholder('-');
    }

    public static function viewContractNo(): TextEntry
    {
        return TextEntry::make('contract_no')
            ->label(__('resources/custom/strings.form.contract_no'))
            ->icon('heroicon-m-clipboard-document-check')
            ->badge()
            ->color('info')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/custom/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/custom/strings.table.created_by'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewCustomNo(): TextEntry
    {
        return TextEntry::make('custom_no')
            ->label(__('resources/custom/strings.form.custom_no'))
            ->badge()
            ->color('primary')
            ->icon('heroicon-m-hashtag')
            ->copyable();
    }

    public static function viewDeclarationNo(): TextEntry
    {
        return TextEntry::make('declaration_no')
            ->label(__('resources/custom/strings.form.declaration_no'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-document-text')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewDocSubmissionDate(): TextEntry
    {
        return TextEntry::make('doc_submission_date')
            ->label(__('resources/custom/strings.form.doc_submission_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewNotes(): TextEntry
    {
        return TextEntry::make('notes')
            ->label(__('resources/custom/strings.form.notes'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewPaymentDueDate(): TextEntry
    {
        return TextEntry::make('payment_due_date')
            ->label(__('resources/custom/strings.form.payment_due_date'))
            ->jalaliDate()
            ->icon('heroicon-m-clock')
            ->placeholder('-');
    }

    public static function viewRegisteredOrder(): TextEntry
    {
        return TextEntry::make('registeredOrder.formatted_name')
            ->label(__('resources/custom/strings.form.registered_order'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->icon('heroicon-m-clipboard-document-list')
            ->copyable();
    }

    public static function viewRialReturnDate(): TextEntry
    {
        return TextEntry::make('rial_return_date')
            ->label(__('resources/custom/strings.form.rial_return_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewShipment(): TextEntry
    {
        return TextEntry::make('shipment.formatted_name')
            ->label(__('resources/custom/strings.form.shipment'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->icon('heroicon-m-truck')
            ->url(fn($record) => $record->shipment ? route('filament.dashboard.resources.shipments.edit', $record->shipment) : null)
            ->placeholder('-');
    }

    public static function viewTenPercentExitDate(): TextEntry
    {
        return TextEntry::make('ten_percent_exit_date')
            ->label(__('resources/custom/strings.form.ten_percent_exit_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/custom/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/custom/strings.table.updated_by'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }
}
