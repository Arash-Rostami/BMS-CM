<?php

namespace App\Filament\Resources\Master\EntityAttributeResource\Traits;

use App\Services\PermissionLabeler;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewEntityType(): TextEntry
    {
        return TextEntry::make('entity_type')
            ->label(__('resources/entityAttribute/strings.infolist.entity_type'))
            ->formatStateUsing(fn ($state): string => PermissionLabeler::getEntityLabel($state))
            ->badge()
            ->icon('heroicon-m-cube');
    }

    public static function viewEntityId(): TextEntry
    {
        return TextEntry::make('entity_id')
            ->label(__('resources/entityAttribute/strings.infolist.entity_id'))
            ->badge()
            ->color('gray')
            ->icon('heroicon-m-hashtag');
    }

    public static function viewKey(): TextEntry
    {
        return TextEntry::make('key')
            ->label(__('resources/entityAttribute/strings.infolist.key'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-key')
            ->copyable();
    }

    public static function viewValue(): TextEntry
    {
        return TextEntry::make('value')
            ->label(__('resources/entityAttribute/strings.infolist.value'))
            ->formatStateUsing(fn ($state): string => is_string($state) ? $state : json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
            ->icon('heroicon-m-document-text')
            ->placeholder('-')
            ->columnSpanFull();
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/entityAttribute/strings.infolist.created_by'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/entityAttribute/strings.infolist.updated_by'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/entityAttribute/strings.infolist.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/entityAttribute/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }
}
