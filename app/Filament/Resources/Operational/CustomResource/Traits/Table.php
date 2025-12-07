<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\CommitmentStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\GuaranteeStatus;
use App\Models\Custom;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

trait Table
{
    public static function showBankGuaranteeStatus(): TextColumn
    {
        return TextColumn::make('bankGuaranteeStatus.name')
            ->label(__('resources/custom/strings.form.bank_guarantee_status'))
            ->badge()
            ->formatStateUsing(fn($record): ?string => $record->bankGuaranteeStatus?->getLocalizedNameAttribute())
            ->color(fn($record): string => GuaranteeStatus::tryFrom($record->bankGuaranteeStatus?->english_name)?->getColor() ?? 'gray')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showClearanceDate(): TextColumn
    {
        return TextColumn::make('clearance_date')
            ->label(__('resources/custom/strings.form.clearance_date'))
            ->date()
            ->formatStateUsing(fn($record) => app()->getLocale() === 'fa' ? toPersianDate($record->clearance_date) : toGregorianDate($record->clearance_date))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showClearanceStatus(): TextColumn
    {
        return TextColumn::make('clearanceStatus.name')
            ->label(__('resources/custom/strings.form.clearance_status'))
            ->badge()
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search) => $query->orWhereHas('clearanceStatus', fn($q) => $q->searchStatus($search)))
            ->formatStateUsing(fn($record): ?string => $record->clearanceStatus?->getLocalizedNameAttribute())
            ->iconPosition(IconPosition::Before)
            ->icon(fn($record): ?string => ClearanceStatus::tryFrom($record->clearanceStatus?->english_name)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->color(fn($record): string => ClearanceStatus::tryFrom($record->clearanceStatus?->english_name)?->getColor() ?? 'gray')
            ->toggleable();

    }

    public static function showClearanceType(): TextColumn
    {
        return TextColumn::make('clearance_type')
            ->label(__('resources/custom/strings.form.clearance_type'))
            ->formatStateUsing(fn(string $state) => match ($state) {
                '90_percent' => '90%',
                '10_percent' => '10%',
                default => '-'
            })
            ->badge()
            ->color(fn(string $state) => match ($state) {
                '90_percent' => 'success',
                '10_percent' => 'warning',
                default => 'gray'
            })
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCommitmentStatus(): TextColumn
    {
        return TextColumn::make('commitmentStatus.name')
            ->label(__('resources/custom/strings.form.commitment_status'))
            ->badge()
            ->formatStateUsing(fn($record): ?string => $record->commitmentStatus?->getLocalizedNameAttribute())
            ->color(fn($record): string => CommitmentStatus::tryFrom($record->commitmentStatus?->english_name)?->getColor() ?? 'gray')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showContractNo(): TextColumn
    {
        return TextColumn::make('contract_no')
            ->label(__('resources/custom/strings.form.contract_no'))
            ->searchable()
            ->copyable()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/custom/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/custom/strings.table.created_by'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCustomNo(): TextColumn
    {
        return TextColumn::make('custom_no')
            ->label(__('resources/custom/strings.form.custom_no'))
            ->searchable()
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn(Custom $record) => $record->contract_no);
    }

    public static function showDeclarationNo(): TextColumn
    {
        return TextColumn::make('declaration_no')
            ->label(__('resources/custom/strings.form.declaration_no'))
            ->searchable()
            ->copyable()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showShipment(): TextColumn
    {
        return TextColumn::make('shipment.shipment_no')
            ->label(__('resources/custom/strings.table.shipment'))
            ->searchable(
                query: fn(Builder $query, string $search) => $query->whereHas('shipment', fn($q) => $q->searchAll($search)),
                isIndividual: true
            )
            ->badge()
            ->sortable()
            ->formatStateUsing(fn(Custom $record) => $record->shipment?->shipment_no)
            ->tooltip(fn(Custom $record) => $record->registeredOrder?->ro_number)
            ->icon('heroicon-o-truck')
            ->color('info')
            ->toggleable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/custom/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/custom/strings.table.updated_by'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
