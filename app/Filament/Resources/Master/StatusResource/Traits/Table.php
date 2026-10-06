<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use App\Filament\Resources\StatusResource;
use App\Jobs\ExportStatuses;
use App\Models\Status;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Collection;

trait Table
{
    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportStatuses')
            ->label(__('resources/status/strings.export.export_statuses'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => StatusResource::canViewAny())
            ->action(function (Collection $records): void {
                ExportStatuses::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showApprovalGate(): IconColumn
    {
        return IconColumn::make('approval_permission')
            ->label(__('resources/status/strings.table.approval_gate'))
            ->state(fn (Status $record): bool => filled($record->approval_permission))
            ->trueIcon('heroicon-o-lock-closed')
            ->falseIcon('heroicon-o-lock-open')
            ->trueColor('warning')
            ->falseColor('gray')
            ->tooltip(fn (Status $record): ?string => $record->approval_permission)
            ->toggleable();
    }

    public static function showStageOrder(): TextColumn
    {
        return TextColumn::make('stage_order')
            ->label(__('resources/status/strings.table.stage_order'))
            ->badge()
            ->color('info')
            ->placeholder('-')
            ->sortable()
            ->toggleable();
    }

    public static function showType(): TextColumn
    {
        return TextColumn::make('type')
            ->label(__('resources/status/strings.table.type'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');
    }

    public static function showEnglishType(): TextColumn
    {
        return TextColumn::make('english_type')
            ->label(__('resources/status/strings.table.english_type'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/status/strings.table.name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');
    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/status/strings.table.english_name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/status/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/status/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/status/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/status/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
