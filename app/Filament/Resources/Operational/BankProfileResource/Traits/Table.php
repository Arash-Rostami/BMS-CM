<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\Operational\BankProfileResource\Enums\Status;
use App\Filament\Resources\Operational\BankProfileResource\Imports\BankProfileImporter;
use App\Jobs\ExportBankProfiles;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait Table
{
    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importBankProfiles')
            ->label(__('resources/bankProfile/strings.import.import_bank_profiles'))
            ->modalHeading(__('resources/bankProfile/strings.import.import_bank_profiles'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(BankProfileImporter::class)
            ->resourceGate(BankProfileResource::class);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportBankProfiles')
            ->label(__('resources/bankProfile/strings.export.export_bank_profiles'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => BankProfileResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportBankProfiles::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function sortByTargetableName(): \Closure
    {
        return function (Builder $query, string $direction): Builder {
            $nameColumn = app()->getLocale() === 'fa' ? 'name' : 'english_name';

            $productQuery = Product::select($nameColumn)
                ->whereColumn('products.id', 'bank_profiles.targetable_id')
                ->where('bank_profiles.targetable_type', Product::class);

            $categoryQuery = Category::select($nameColumn)
                ->whereColumn('categories.id', 'bank_profiles.targetable_id')
                ->where('bank_profiles.targetable_type', Category::class);

            return $query->orderByRaw(
                "COALESCE(({$productQuery->toSql()}), ({$categoryQuery->toSql()})) {$direction}",
                [...$productQuery->getBindings(), ...$categoryQuery->getBindings()]
            );
        };
    }

    public static function showBank(): TextColumn
    {
        return TextColumn::make('bank.name')
            ->label(__('resources/bankProfile/strings.table.bank'))
            ->sortable()
            ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('bank', fn ($q) => $q->searchByName($search)))
            ->formatStateUsing(fn ($record): ?string => $record->bank?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showBpNumber(): TextColumn
    {
        return TextColumn::make('bp_number')
            ->label(__('resources/bankProfile/strings.table.bp_number'))
            ->searchable(query: fn (Builder $query, string $search): Builder => BankProfileResource::orWhereExtraAttributesMatch(
                $query->where('bp_number', 'like', "%{$search}%"),
                $search
            ))
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn ($record) => '🔗 '.$record->registeredOrder?->contract_no ?? $record->registeredOrder?->ro_number);
    }

    public static function showCompany(): TextColumn
    {
        return TextColumn::make('company.name')
            ->label(__('resources/bankProfile/strings.table.company'))
            ->sortable()
            ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('company', fn ($q) => $q->searchByName($search)))
            ->formatStateUsing(fn ($record): ?string => $record->company?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private static function isSettledStatus($record): bool
    {
        return in_array(Status::tryFrom($record?->status?->english_name), [Status::Received, Status::Rejected], true);
    }

    public static function isCommitmentOverdue($record): bool
    {
        return $record?->commitment_payment_date
            && $record->commitment_payment_date->isPast()
            && ! static::isSettledStatus($record);
    }

    public static function isPaymentOverdue($record): bool
    {
        return $record?->payment_due_date
            && $record->payment_due_date->isPast()
            && ! static::isSettledStatus($record);
    }

    public static function showCommitmentPaymentDate(): TextColumn
    {
        return TextColumn::make('commitment_payment_date')
            ->label(__('resources/bankProfile/strings.table.commitment_payment_date'))
            ->adaptiveDate()
            ->sortable()
            ->badge(fn ($record): bool => static::isCommitmentOverdue($record))
            ->color(fn ($record): ?string => static::isCommitmentOverdue($record) ? 'danger' : null)
            ->icon(fn ($record): ?string => static::isCommitmentOverdue($record) ? 'heroicon-o-exclamation-triangle' : null)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPaymentDueDate(): TextColumn
    {
        return TextColumn::make('payment_due_date')
            ->label(__('resources/bankProfile/strings.table.payment_due_date'))
            ->adaptiveDate()
            ->sortable()
            ->badge(fn ($record): bool => static::isPaymentOverdue($record))
            ->color(fn ($record): ?string => static::isPaymentOverdue($record) ? 'danger' : null)
            ->icon(fn ($record): ?string => static::isPaymentOverdue($record) ? 'heroicon-o-exclamation-triangle' : null)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/bankProfile/strings.table.created_at'))->adaptiveDateTime()
            ->adaptiveDateTime()
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
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('bank_profiles.id', 'like', "%{$search}%"))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showRequestedAmount(): TextColumn
    {
        return TextColumn::make('requested_amount')
            ->label(__('resources/bankProfile/strings.table.requested_amount'))
            ->formatStateUsing(fn ($state) => delimiter($state))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showRequestedCurrency(): TextColumn
    {
        return TextColumn::make('requestedCurrency.name')
            ->label(__('resources/bankProfile/strings.table.requested_currency'))
            ->sortable()
            ->searchable()
            ->formatStateUsing(fn ($record): ?string => $record->requestedCurrency?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showRegisteredOrder(): TextColumn
    {
        return TextColumn::make('registeredOrder.ro_number')
            ->label(__('resources/bankProfile/strings.table.registered_order'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas(
                    'registeredOrder',
                    fn (Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->sortable()
            ->tooltip(fn (Model $record) => ' 💼 '.$record->registeredOrder?->contract_no ?? ' ')
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
            ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('status', fn ($q) => $q->searchByName($search)))
            ->formatStateUsing(fn ($record): ?string => $record->status?->getLocalizedNameAttribute())
            ->iconPosition(IconPosition::Before)
            ->icon(fn ($record): ?string => Status::tryFrom($record->status?->english_name)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->color(fn ($record): string => Status::tryFrom($record->status?->english_name)?->getColor() ?? 'gray');
    }

    public static function showTargetable(): TextColumn
    {
        return TextColumn::make('targetable.name')
            ->label(__('resources/bankProfile/strings.table.targetable'))
            ->sortable(query: static::sortByTargetableName())
            ->searchable(true, fn ($query, string $search) => $query->SearchTargetable($search), false)
            ->formatStateUsing(function (Model $record) {
                if (! $record->targetable) {
                    return '-';
                }

                return $record->targetable_type === Product::class ? $record->targetable?->customized_label ?? $record->targetable?->getLocalizedNameAttribute() : $record->targetable?->getLocalizedNameAttribute();
            })
            ->badge()
            ->color(fn ($record) => $record->targetable_type === Product::class ? 'success' : 'warning')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/bankProfile/strings.table.updated_at'))
            ->adaptiveDateTime()
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
