<?php

namespace App\Filament\Resources\Master\DepartmentResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\Master\DepartmentResource\Imports\DepartmentImporter;
use App\Jobs\ExportDepartments;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Collection;

trait Table
{
    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importDepartments')
            ->label(__('resources/department/strings.import.import_departments'))
            ->modalHeading(__('resources/department/strings.import.import_departments'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(DepartmentImporter::class)
            ->resourceGate(DepartmentResource::class);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportDepartments')
            ->label(__('resources/department/strings.export.export_departments'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => DepartmentResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportDepartments::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showCode(): TextColumn
    {
        return TextColumn::make('code')
            ->label(__('resources/department/strings.table.code'))
            ->badge()
            ->copyable()
            ->sortable()
            ->searchable();
    }

    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/department/strings.table.name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');
    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/department/strings.table.english_name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showDescription(): TextColumn
    {
        return TextColumn::make('description')
            ->label(__('resources/department/strings.table.description'))
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false)
            ->limit(50);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/department/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/department/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/department/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/department/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showIsActive(): ToggleColumn
    {
        return ToggleColumn::make('is_active')
            ->label(__('resources/department/strings.table.is_active'))
            ->disabled(fn ($record): bool => ! DepartmentResource::canEdit($record))
            ->onIcon('heroicon-o-check-circle')
            ->offIcon('heroicon-o-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->toggleable()
            ->sortable();
    }
}
