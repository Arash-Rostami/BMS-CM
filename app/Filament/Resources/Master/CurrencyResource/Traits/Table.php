<?php

namespace App\Filament\Resources\Master\CurrencyResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\Master\CurrencyResource\Imports\CurrencyImporter;
use App\Jobs\ExportCurrencies;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait Table
{
    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importCurrencies')
            ->label(__('resources/currency/strings.import.import_currencies'))
            ->modalHeading(__('resources/currency/strings.import.import_currencies'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(CurrencyImporter::class)
            ->authorize(fn (): bool => CurrencyResource::canCreate());
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportCurrencies')
            ->label(__('resources/currency/strings.export.export_currencies'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => CurrencyResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportCurrencies::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/currency/strings.table.name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');

    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/currency/strings.table.english_name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showDescription(): TextColumn
    {
        return TextColumn::make('description')
            ->label(__('resources/currency/strings.table.description'))
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false)
            ->limit(50);
    }

    public static function showIsActive(): ToggleColumn
    {
        return ToggleColumn::make('is_active')
            ->label(__('resources/currency/strings.table.is_active'))
            ->onIcon('heroicon-o-check-circle')
            ->offIcon('heroicon-o-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->toggleable()
            ->sortable();
    }

    public static function showInUse(): IconColumn
    {
        return IconColumn::make('in_use')
            ->label(__('resources/currency/strings.table.in_use'))
            ->getStateUsing(fn ($record) => collect(static::usageRelations())
                ->sum(fn (string $relation) => (int) ($record->{Str::snake($relation).'_count'} ?? 0)) > 0)
            ->boolean()
            ->trueIcon('heroicon-o-link')
            ->falseIcon('heroicon-o-link-slash')
            ->trueColor('warning')
            ->falseColor('gray')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/currency/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/currency/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/currency/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/currency/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
