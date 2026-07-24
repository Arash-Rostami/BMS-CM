<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/status/strings.infolist.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/status/strings.infolist.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewEnglishName(): TextEntry
    {
        return TextEntry::make('english_name')
            ->label(__('resources/status/strings.infolist.english_name'))
            ->icon('heroicon-m-language')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewEnglishType(): TextEntry
    {
        return TextEntry::make('english_type')
            ->label(__('resources/status/strings.infolist.english_type'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-language')
            ->placeholder('-');
    }

    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/status/strings.infolist.name'))
            ->icon('heroicon-m-tag')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewType(): TextEntry
    {
        return TextEntry::make('type')
            ->label(__('resources/status/strings.infolist.type'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-rectangle-stack')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/status/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/status/strings.infolist.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }
}
