<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\CommitmentStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\GuaranteeStatus;
use App\Filament\Resources\Operational\CustomResource\Imports\CustomImporter;
use App\Jobs\ExportCustoms;
use App\Models\Custom;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait Table
{
    private const AGING_WARNING_THRESHOLD_DAYS = 7;

    private const AGING_DANGER_THRESHOLD_DAYS = 14;

    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importCustoms')
            ->label(__('resources/custom/strings.import.import_customs'))
            ->modalHeading(__('resources/custom/strings.import.import_customs'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(CustomImporter::class)
            ->resourceGate(CustomResource::class);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportCustoms')
            ->label(__('resources/custom/strings.export.export_customs'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => CustomResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportCustoms::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showBankGuaranteeStatus(): TextColumn
    {
        return TextColumn::make('bankGuaranteeStatus.name')
            ->label(__('resources/custom/strings.form.bank_guarantee_status'))
            ->badge()
            ->formatStateUsing(fn ($record): ?string => $record->bankGuaranteeStatus?->getLocalizedNameAttribute())
            ->color(fn ($record): string => GuaranteeStatus::tryFrom($record->bankGuaranteeStatus?->english_name)?->getColor() ?? 'gray')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function clearanceAgingColor(?int $days): string
    {
        return match (true) {
            $days === null => 'gray',
            $days > self::AGING_DANGER_THRESHOLD_DAYS => 'danger',
            $days >= self::AGING_WARNING_THRESHOLD_DAYS => 'warning',
            default => 'success',
        };
    }

    public static function showClearanceAgingDays(): TextColumn
    {
        return TextColumn::make('clearance_aging_days')
            ->label(__('resources/custom/strings.table.clearance_aging_days'))
            ->formatStateUsing(fn (?int $state) => $state === null ? '-' : $state.' '.__('resources/custom/strings.table.days'))
            ->badge()
            ->color(fn (?int $state): string => static::clearanceAgingColor($state))
            ->icon('heroicon-o-clock')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showClearanceDate(): TextColumn
    {
        return TextColumn::make('clearance_date')
            ->label(__('resources/custom/strings.form.clearance_date'))
            ->adaptiveDate()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showClearanceStatus(): TextColumn
    {
        return TextColumn::make('clearanceStatus.name')
            ->label(__('resources/custom/strings.form.clearance_status'))
            ->badge()
            ->sortable()
            ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('clearanceStatus', fn ($q) => $q->searchStatus($search)))
            ->formatStateUsing(fn ($record): ?string => $record->clearanceStatus?->getLocalizedNameAttribute())
            ->iconPosition(IconPosition::Before)
            ->icon(fn ($record): ?string => ClearanceStatus::tryFrom($record->clearanceStatus?->english_name)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->color(fn ($record): string => ClearanceStatus::tryFrom($record->clearanceStatus?->english_name)?->getColor() ?? 'gray')
            ->toggleable();

    }

    public static function showClearanceType(): TextColumn
    {
        return TextColumn::make('clearance_type')
            ->label(__('resources/custom/strings.form.clearance_type'))
            ->formatStateUsing(fn (?string $state) => $state
                ? (__('resources/custom/strings.general.clearance_types')[$state] ?? $state)
                : '-')
            ->badge()
            ->color(fn (?string $state) => match ($state) {
                'definitive' => 'success',
                'percentage' => 'warning',
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
            ->formatStateUsing(fn ($record): ?string => $record->commitmentStatus?->getLocalizedNameAttribute())
            ->color(fn ($record): string => CommitmentStatus::tryFrom($record->commitmentStatus?->english_name)?->getColor() ?? 'gray')
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
            ->adaptiveDateTime()
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
            ->searchable(query: fn (Builder $query, string $search): Builder => CustomResource::orWhereExtraAttributesMatch(
                $query->where('custom_no', 'like', "%{$search}%"),
                $search
            ))
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn (Custom $record) => $record->contract_no);
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

    public static function isOpenExposure(?Custom $record): bool
    {
        return $record?->clearance_type === 'percentage'
            && (GuaranteeStatus::tryFrom($record->bankGuaranteeStatus?->english_name) !== GuaranteeStatus::Returned
                || CommitmentStatus::tryFrom($record->commitmentStatus?->english_name) !== CommitmentStatus::Completed);
    }

    public static function showExposureFlag(): TextColumn
    {
        return TextColumn::make('exposure_flag')
            ->label(__('resources/custom/strings.table.exposure_flag'))
            ->getStateUsing(fn ($record): ?string => static::isOpenExposure($record)
                ? __('resources/custom/strings.table.exposure_flag')
                : null)
            ->badge()
            ->color('danger')
            ->icon('heroicon-o-exclamation-triangle')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showShipment(): TextColumn
    {
        return TextColumn::make('shipment.shipment_no')
            ->label(__('resources/custom/strings.table.shipment'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas('shipment', fn ($q) => $q->searchAll($search)),
                isIndividual: true
            )
            ->badge()
            ->sortable()
            ->formatStateUsing(fn (Custom $record) => $record->shipment?->shipment_no)
            ->tooltip(fn (Custom $record) => $record->registeredOrder?->ro_number)
            ->icon('heroicon-o-truck')
            ->color('info')
            ->toggleable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/custom/strings.table.updated_at'))
            ->adaptiveDateTime()
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
