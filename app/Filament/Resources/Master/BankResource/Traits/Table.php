<?php

namespace App\Filament\Resources\Master\BankResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\BankResource;
use App\Filament\Resources\Master\BankResource\Imports\BankImporter;
use App\Jobs\ExportBanks;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Collection;

trait Table
{
    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importBanks')
            ->label(__('resources/bank/strings.import.import_banks'))
            ->modalHeading(__('resources/bank/strings.import.import_banks'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(BankImporter::class)
            ->resourceGate(BankResource::class);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportBanks')
            ->label(__('resources/bank/strings.export.export_banks'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => BankResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportBanks::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

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
            ->label(__('resources/bank/strings.table.name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');
    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/bank/strings.table.english_name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showDescription(): TextColumn
    {
        return TextColumn::make('description')
            ->label(__('resources/bank/strings.table.description'))
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false)
            ->limit(50);

    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/bank/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/bank/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/bank/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/bank/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showIsActive(): ToggleColumn
    {
        return ToggleColumn::make('is_active')
            ->label(__('resources/bank/strings.table.is_active'))
            ->onIcon('heroicon-o-check-circle')
            ->offIcon('heroicon-o-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->toggleable()
            ->sortable();
    }
}
