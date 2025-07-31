<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components;

trait Infolist
{
    public static function viewType(): TextEntry
    {
        return Components\TextEntry::make('type')
            ->label(__('resources/status/strings.form.type'));
    }

    public static function viewEnglishType(): TextEntry
    {
        return Components\TextEntry::make('english_type')
            ->label(__('resources/status/strings.form.english_type'));
    }

    public static function viewName(): TextEntry
    {
        return Components\TextEntry::make('name')
            ->label(__('resources/status/strings.form.name'));
    }

    public static function viewEnglishName(): TextEntry
    {
        return Components\TextEntry::make('english_name')
            ->label(__('resources/status/strings.form.english_name'));
    }

    public static function viewCreator(): TextEntry
    {
        return Components\TextEntry::make('creator.name')
            ->label(__('resources/status/strings.form.creator'));
    }

    public static function viewUpdater(): TextEntry
    {
        return Components\TextEntry::make('updater.name')
            ->label(__('resources/status/strings.form.updater'));
    }

    public static function viewCreatedAt(): TextEntry
    {
        return Components\TextEntry::make('created_at')
            ->label(__('resources/status/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return Components\TextEntry::make('updated_at')
            ->label(__('resources/status/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s');
    }
}
