<?php

namespace App\Filament\Resources\Master\CategoryResource\Traits;

use App\Filament\Resources\Master\CategoryResource\Enums\Level;
use App\Filament\Resources\Master\CategoryResource\Enums\Status;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

trait Table
{
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
            ->formatStateUsing(fn($state): string => Level::fromLevel($state)->getLabel())
            ->icon(fn($state): string => Level::fromLevel($state)->getIcon())
            ->color(fn($state): string => Level::fromLevel($state)->getColor())
            ->toggleable()
            ->sortable();
    }

    public static function showParent(): TextColumn
    {
        return TextColumn::make('parent.name')
            ->label(__('resources/category/strings.table.parent'))
            ->formatStateUsing(fn($record, $state) => app()->getLocale() != 'fa' ? optional($record->parent)->english_name : $state)
            ->toggleable()
            ->sortable();
    }

    public static function showActive(): IconColumn
    {
        return IconColumn::make('active')
            ->boolean()
            ->label(__('resources/category/strings.table.active'))
            ->toggleable()
            ->icon(fn(bool $state): string => Status::tryFrom((int)$state)?->getIcon() ?? 'heroicon-o-x-circle')
            ->color(fn(bool $state): string => Status::tryFrom((int)$state)?->getColor() ?? 'gray');
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
            ->dateTime()
            ->sortable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/category/strings.table.updated_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->dateTime()
            ->sortable();
    }
}
