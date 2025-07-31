<?php

namespace App\Filament\Resources\Master\CategoryResource\Traits;

use App\Filament\Resources\Master\CategoryResource\Enums\Level;
use App\Filament\Resources\Master\CategoryResource\Enums\Status;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/category/strings.form.name'));
    }

    public static function viewEnglishName(): TextEntry
    {
        return TextEntry::make('english_name')
            ->label(__('resources/category/strings.form.english_name'));
    }

    public static function viewDescription(): TextEntry
    {
        return TextEntry::make('description')
            ->label(__('resources/category/strings.form.description'));
    }

    public static function viewParent(): TextEntry
    {
        return TextEntry::make('parent.name')
            ->label(__('resources/category/strings.form.parent'))
            ->formatStateUsing(fn($record, $state) => app()->getLocale() != 'fa' ? optional($record->parent)->english_name : $state);
    }

    public static function viewLevel(): TextEntry
    {
        return TextEntry::make('level')
            ->label(__('resources/category/strings.form.level'))
            ->formatStateUsing(fn($state): string => Level::fromLevel($state)?->getLabel() ?? $state)
            ->color(fn($state): string => Level::fromLevel($state)?->getColor());
    }

    public static function viewActive(): TextEntry
    {
        return TextEntry::make('active')
            ->label(__('resources/category/strings.table.active'))
            ->formatStateUsing(fn($state): string => Status::tryFrom((int)$state)?->getLabel() ?? $state)
            ->color(fn($state): string => Status::tryFrom((int)$state)?->getColor() ?? 'gray');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/category/strings.form.creator'));
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/category/strings.form.updater'));
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/category/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/category/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s');
    }
}
