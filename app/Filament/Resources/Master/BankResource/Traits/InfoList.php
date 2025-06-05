<?php

namespace App\Filament\Resources\Master\BankResource\Traits;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components;


trait InfoList
{
    public static function viewName(): TextEntry
    {
        return Components\TextEntry::make('name')
            ->label(__('resources/bank/strings.form.name'));
    }

    public static function viewEnglishName(): TextEntry
    {
        return Components\TextEntry::make('english_name')
            ->label(__('resources/bank/strings.form.english_name'));
    }

    public static function viewDescription(): TextEntry
    {
        return Components\TextEntry::make('description')
            ->label(__('resources/bank/strings.form.description'));
    }

    public static function viewCreator(): TextEntry
    {
        return Components\TextEntry::make('creator.name')
            ->label(__('resources/bank/strings.form.creator'));
    }

    public static function viewUpdater(): TextEntry
    {
        return Components\TextEntry::make('updater.name')
            ->label(__('resources/bank/strings.form.updater'));
    }

    public static function viewCreatedAt(): TextEntry
    {
        return Components\TextEntry::make('created_at')
            ->label(__('resources/bank/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return Components\TextEntry::make('updated_at')
            ->label(__('resources/bank/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s');
    }
}
