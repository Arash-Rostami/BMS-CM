<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use App\Filament\Resources\StatusResource;
use App\Jobs\ExportStatuses;
use App\Models\Permission;
use App\Models\Status;
use App\Services\SmartCacheManager;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Collection;

trait Table
{
    protected static function stageOrderProblemTypes(): array
    {
        return SmartCacheManager::remember('Status', ['type' => 'stage_order_problem_types'], 60, fn () => Status::query()
            ->whereNotNull('stage_order')
            ->get(['english_type', 'stage_order'])
            ->groupBy('english_type')
            ->filter(function (Collection $rows): bool {
                $orders = $rows->pluck('stage_order')->map(fn ($value) => (int) $value);
                $distinct = $orders->unique();

                if ($distinct->count() !== $orders->count()) {
                    return true;
                }

                return $distinct->max() - $distinct->min() + 1 !== $distinct->count();
            })
            ->keys()
            ->all());
    }

    public static function hasStageOrderIssue(Status $record): bool
    {
        return filled($record->stage_order) && in_array($record->english_type, static::stageOrderProblemTypes(), true);
    }

    protected static function zeroUserApprovalPermissions(): array
    {
        return SmartCacheManager::remember('Status', ['type' => 'zero_user_approval_permissions'], 60, function () {
            $names = Status::query()->whereNotNull('approval_permission')->distinct()->pluck('approval_permission');

            if ($names->isEmpty()) {
                return [];
            }

            $counts = Permission::query()->whereIn('name', $names)->withCount('users')->pluck('users_count', 'name');

            return $names->filter(fn (string $name) => ($counts[$name] ?? 0) === 0)->values()->all();
        });
    }

    public static function approvalGateUnreachable(Status $record): bool
    {
        return filled($record->approval_permission) && in_array($record->approval_permission, static::zeroUserApprovalPermissions(), true);
    }

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
            ->icon(fn (bool $state, Status $record): string => match (true) {
                ! $state => 'heroicon-o-lock-open',
                static::approvalGateUnreachable($record) => 'heroicon-o-exclamation-triangle',
                default => 'heroicon-o-lock-closed',
            })
            ->color(fn (bool $state, Status $record): string => match (true) {
                ! $state => 'gray',
                static::approvalGateUnreachable($record) => 'danger',
                default => 'warning',
            })
            ->tooltip(fn (bool $state, Status $record): ?string => match (true) {
                ! $state => null,
                static::approvalGateUnreachable($record) => __('resources/status/strings.table.approval_gate_unreachable'),
                default => $record->approval_permission,
            })
            ->toggleable();
    }

    public static function showStageOrder(): TextColumn
    {
        return TextColumn::make('stage_order')
            ->label(__('resources/status/strings.table.stage_order'))
            ->badge()
            ->color(fn (Status $record) => static::hasStageOrderIssue($record) ? 'danger' : 'info')
            ->icon(fn (Status $record) => static::hasStageOrderIssue($record) ? 'heroicon-o-exclamation-triangle' : null)
            ->tooltip(fn (Status $record) => static::hasStageOrderIssue($record)
                ? __('resources/status/strings.table.stage_order_issue')
                : null)
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
