<?php

namespace App\Filament\Resources\Master\CurrencyResource\Traits;

use Filament\Infolists\Components;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewName(): TextEntry
    {
        return Components\TextEntry::make('name')
            ->label(__('resources/currency/strings.form.name'));
    }

    public static function viewEnglishName(): TextEntry
    {
        return Components\TextEntry::make('english_name')
            ->label(__('resources/currency/strings.form.english_name'));
    }

    public static function viewDescription(): TextEntry
    {
        return Components\TextEntry::make('description')
            ->label(__('resources/currency/strings.form.description'));
    }

    public static function viewCreator(): TextEntry
    {
        return Components\TextEntry::make('creator.name')
            ->label(__('resources/currency/strings.form.creator'));
    }

    public static function viewUpdater(): TextEntry
    {
        return Components\TextEntry::make('updater.name')
            ->label(__('resources/currency/strings.form.updater'));
    }

    public static function viewCreatedAt(): TextEntry
    {
        return Components\TextEntry::make('created_at')
            ->label(__('Created At'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return Components\TextEntry::make('updated_at')
            ->label(__('Updated At'))
            ->dateTime('M Y | D: H:i:s');
    }
}
