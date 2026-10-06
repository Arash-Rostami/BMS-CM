<?php

namespace App\Filament\Resources\Master\CompanyResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\CompanyResource;
use App\Filament\Resources\Master\CompanyResource\Enums\Type;
use App\Filament\Resources\Master\CompanyResource\Imports\CompanyImporter;
use App\Jobs\ExportCompanies;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Collection;

trait Table
{
    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importCompanies')
            ->label(__('resources/company/strings.import.import_companies'))
            ->modalHeading(__('resources/company/strings.import.import_companies'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(CompanyImporter::class)
            ->resourceGate(CompanyResource::class);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportCompanies')
            ->label(__('resources/company/strings.export.export_companies'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => CompanyResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportCompanies::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

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
            ->label(__('resources/company/strings.table.name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');

    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/company/strings.table.english_name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showDescription(): TextColumn
    {
        return TextColumn::make('description')
            ->label(__('resources/company/strings.table.description'))
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false)
            ->limit(50)
            ->tooltip(fn ($record) => $record->description);

    }

    public static function showCompanyTypes(): TextColumn
    {

        return TextColumn::make('formatted_types')
            ->label(__('resources/company/strings.table.company_types'))
            ->badge()
            ->separator(', ')
            ->color(fn (?string $state): ?string => Type::tryFromLocalised($state)?->getColor() ?? 'info')
            ->icon(fn (?string $state): ?string => Type::tryFromLocalised($state)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->searchable(query: fn ($query, $search) => $query->whereJsonContains('types', strtolower($search)))
            ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("JSON_LENGTH(types) {$direction}"))
            ->placeholder(__('resources/company/strings.table.no_types'))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showIsActive(): ToggleColumn
    {
        return ToggleColumn::make('is_active')
            ->label(__('resources/company/strings.table.is_active'))
            ->onIcon('heroicon-o-check-circle')
            ->offIcon('heroicon-o-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->toggleable()
            ->sortable();
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/company/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/company/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/company/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/company/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
