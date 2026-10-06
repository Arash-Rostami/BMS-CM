<?php

namespace App\Filament\Resources\Master\CategoryResource\Traits;

use App\Filament\Actions\ImportAction;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Master\CategoryResource\Enums\Level;
use App\Filament\Resources\Master\CategoryResource\Enums\Status;
use App\Filament\Resources\Master\CategoryResource\Imports\CategoryImporter;
use App\Jobs\ExportCategories;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait Table
{
    public static function getImportAction(): ImportAction
    {
        return ImportAction::make('importCategories')
            ->label(__('resources/category/strings.import.import_categories'))
            ->modalHeading(__('resources/category/strings.import.import_categories'))
            ->icon('heroicon-o-arrow-up-tray')
            ->importer(CategoryImporter::class)
            ->resourceGate(CategoryResource::class);
    }

    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportCategories')
            ->label(__('resources/category/strings.export.export_categories'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => CategoryResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportCategories::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (Model $record, DeleteAction $action): void {
                if (! static::hasBlockingRelations($record)) {
                    return;
                }

                static::sendDeleteBlockedNotification($record->children->count(), $record->products->count());

                $action->halt();
            });
    }

    public static function getDeleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->before(function (Collection $records, DeleteBulkAction $action): void {
                $blocked = $records->filter(fn (Model $record): bool => static::hasBlockingRelations($record));

                if ($blocked->isEmpty()) {
                    return;
                }

                Notification::make()
                    ->title(__('resources/category/strings.general.bulk_delete_blocked', ['count' => $blocked->count()]))
                    ->danger()
                    ->send();

                $action->halt();
            });
    }

    protected static function hasBlockingRelations(Model $record): bool
    {
        return $record->children->isNotEmpty() || $record->products->isNotEmpty();
    }

    protected static function sendDeleteBlockedNotification(int $childrenCount, int $productsCount): void
    {
        Notification::make()
            ->title(__('resources/category/strings.general.delete_blocked', [
                'children' => $childrenCount,
                'products' => $productsCount,
            ]))
            ->danger()
            ->send();
    }

    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/category/strings.table.name'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa')
            ->searchable();
    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/category/strings.table.english_name'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa')
            ->searchable();
    }

    public static function showLevel(): TextColumn
    {
        return TextColumn::make('level')
            ->label(__('resources/category/strings.table.level'))
            ->formatStateUsing(fn ($state): string => Level::fromLevel($state)->getLabel())
            ->icon(fn ($state): string => Level::fromLevel($state)->getIcon())
            ->color(fn ($state): string => Level::fromLevel($state)->getColor())
            ->toggleable()
            ->sortable();
    }

    public static function showParent(): TextColumn
    {
        return TextColumn::make('parent.name')
            ->label(__('resources/category/strings.table.parent'))
            ->formatStateUsing(fn ($record, $state) => app()->getLocale() != 'fa' ? optional($record->parent)->english_name : $state)
            ->toggleable()
            ->sortable();
    }

    public static function showActive(): IconColumn
    {
        return IconColumn::make('active')
            ->boolean()
            ->label(__('resources/category/strings.table.active'))
            ->toggleable()
            ->icon(fn (bool $state): string => Status::tryFrom((int) $state)?->getIcon() ?? 'heroicon-o-x-circle')
            ->color(fn (bool $state): string => Status::tryFrom((int) $state)?->getColor() ?? 'gray');
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/category/strings.table.creator'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/category/strings.table.updater'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/category/strings.table.created_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->adaptiveDateTime()
            ->sortable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/category/strings.table.updated_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->adaptiveDateTime()
            ->sortable();
    }
}
