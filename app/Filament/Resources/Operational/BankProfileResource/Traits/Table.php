<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use App\Filament\Resources\Operational\BankProfileResource\Enums\Status;
use App\Models\Product;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait Table
{
    public static function showBank(): TextColumn
    {
        return TextColumn::make('bank.name')
            ->label(__('resources/bankProfile/strings.table.bank'))
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search) => $query->whereHas('bank', fn($q) => $q->searchByName($search)))
            ->formatStateUsing(fn($record): ?string => $record->bank?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showBpNumber(): TextColumn
    {
        return TextColumn::make('bp_number')
            ->label(__('resources/bankProfile/strings.table.bp_number'))
            ->searchable()
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn($record) => '🔗 ' . $record->registeredOrder?->contract_no ?? $record->registeredOrder?->ro_number);
    }

    public static function showCompany(): TextColumn
    {
        return TextColumn::make('company.name')
            ->label(__('resources/bankProfile/strings.table.company'))
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search) => $query->whereHas('company', fn($q) => $q->searchByName($search)))
            ->formatStateUsing(fn($record): ?string => $record->company?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCommitmentPaymentDate(): TextColumn
    {
        return TextColumn::make('commitment_payment_date')
            ->label(__('resources/bankProfile/strings.table.commitment_payment_date'))
            ->date()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPaymentDueDate(): TextColumn
    {
        return TextColumn::make('payment_due_date')
            ->label(__('resources/bankProfile/strings.table.payment_due_date'))
            ->date()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/bankProfile/strings.table.created_at'))->dateTime()
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/bankProfile/strings.table.created_by'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showId(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/bankProfile/strings.table.id'))
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search): Builder => $query->where('bank_profiles.id', 'like', "%{$search}%"))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showRegisteredOrder(): TextColumn
    {
        return TextColumn::make('registeredOrder.ro_number')
            ->label(__('resources/bankProfile/strings.table.registered_order'))
            ->searchable(
                query: fn(Builder $query, string $search) => $query->whereHas(
                    'registeredOrder',
                    fn(Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->sortable()
            ->tooltip(fn(Model $record) => ' 💼 ' .$record->registeredOrder?->contract_no ?? ' ')
            ->iconPosition(IconPosition::Before)
            ->icon('heroicon-o-document-check')
            ->badge()
            ->color('info')
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.name')
            ->label(__('resources/bankProfile/strings.table.status'))
            ->badge()
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search) => $query->orWhereHas('status', fn($q) => fn($q) => $q->searchByName($search)))
            ->formatStateUsing(fn($record): ?string => $record->status?->getLocalizedNameAttribute())
            ->iconPosition(IconPosition::Before)
            ->icon(fn($record): ?string => Status::tryFrom($record->status?->english_name)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->color(fn($record): string => Status::tryFrom($record->status?->english_name)?->getColor() ?? 'gray');
    }

    public static function showTargetable(): TextColumn
    {
        return TextColumn::make('targetable.name')
            ->label(__('resources/bankProfile/strings.table.targetable'))
            ->sortable(['targetable_type', 'targetable_id'])
            ->searchable(true, fn($query, string $search) => $query->SearchTargetable($search), false)
            ->formatStateUsing(function (Model $record) {
                if (!$record->targetable) return '-';
                return $record->targetable_type === Product::class ? $record->targetable?->customized_label ?? $record->targetable?->getLocalizedNameAttribute() : $record->targetable?->getLocalizedNameAttribute();
            })
            ->badge()
            ->color(fn($record) => $record->targetable_type === Product::class ? 'success' : 'warning')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/bankProfile/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/bankProfile/strings.table.updated_by'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
