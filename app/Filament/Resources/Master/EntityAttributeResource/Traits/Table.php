<?php

namespace App\Filament\Resources\Master\EntityAttributeResource\Traits;

use App\Models\EntityAttribute;
use App\Services\PermissionLabeler;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

trait Table
{
    protected static array $existingOwners = [];

    public static function showId(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/entityAttribute/strings.table.id'))
            ->badge()
            ->color('gray')
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/entityAttribute/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/entityAttribute/strings.table.created_by'))
            ->sortable();
    }

    public static function showEntityId(): TextColumn
    {
        return TextColumn::make('entity_id')
            ->label(__('resources/entityAttribute/strings.table.entity_id'))
            ->formatStateUsing(fn ($state, EntityAttribute $record): string => static::ownerLabel($record))
            ->url(function (EntityAttribute $record, $livewire): ?string {
                $page = $livewire->getTableRecords();
                $page = $page instanceof Paginator ? collect($page->items()) : collect($page);

                return static::ownerUrl($record, $page);
            })
            ->badge()
            ->color('gray')
            ->sortable();
    }

    public static function ownerLabel(EntityAttribute $record): string
    {
        return PermissionLabeler::getEntityLabel($record->entity_type).' #'.$record->entity_id;
    }

    public static function ownerUrl(EntityAttribute $record, ?Collection $siblings = null): ?string
    {
        $routes = collect(config('workspace.resources'))->pluck('route', 'model');
        $type = $record->entity_type;

        $resource = $routes->has($type) ? Filament::getModelResource($type) : null;

        if (! $resource || ! $resource::canEdit(new $type)) {
            return null;
        }

        $ids = ($siblings ?? collect([$record]))->where('entity_type', $type)->pluck('entity_id')->unique()->values();
        $memo = $type.':'.md5($ids->implode(','));

        static::$existingOwners[$memo] ??= $type::query()->whereKey($ids)->pluck('id')->all();

        return in_array($record->entity_id, static::$existingOwners[$memo])
            ? route($routes[$type], ['record' => $record->entity_id])
            : null;
    }

    public static function showEntityType(): TextColumn
    {
        return TextColumn::make('entity_type')
            ->label(__('resources/entityAttribute/strings.table.entity_type'))
            ->formatStateUsing(fn ($state): string => PermissionLabeler::getEntityLabel($state))
            ->badge()
            ->searchable()
            ->sortable();
    }

    public static function showKey(): TextColumn
    {
        return TextColumn::make('key')
            ->label(__('resources/entityAttribute/strings.table.key'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-key')
            ->searchable()
            ->sortable()
            ->copyable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/entityAttribute/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/entityAttribute/strings.table.updated_by'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showValue(): TextColumn
    {
        return TextColumn::make('value')
            ->label(__('resources/entityAttribute/strings.table.value'))
            ->formatStateUsing(fn ($state): string => match (true) {
                is_string($state) => $state,
                is_null($state) => '',
                default => json_encode($state, JSON_UNESCAPED_UNICODE),
            })
            ->toggleable(isToggledHiddenByDefault: true)
            ->limit(50)
            ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
            ->searchable();
    }
}
