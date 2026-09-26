<?php

namespace App\Filament\Resources\Master\DepartmentResource\Traits;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewCode(): TextEntry
    {
        return TextEntry::make('code')
            ->label(__('resources/department/strings.infolist.code'))
            ->icon('heroicon-m-key')
            ->copyable()
            ->badge()
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/department/strings.infolist.created_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/department/strings.infolist.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewDescription(): TextEntry
    {
        return TextEntry::make('description')
            ->label(__('resources/department/strings.infolist.description'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewEnglishName(): TextEntry
    {
        return TextEntry::make('english_name')
            ->label(__('resources/department/strings.infolist.english_name'))
            ->icon('heroicon-m-language')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewIsActive(): IconEntry
    {
        return IconEntry::make('is_active')
            ->label(__('resources/department/strings.infolist.is_active'))
            ->boolean()
            ->trueIcon('heroicon-m-check-circle')
            ->falseIcon('heroicon-m-x-circle')
            ->trueColor('success')
            ->falseColor('danger');
    }

    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/department/strings.infolist.name'))
            ->icon('heroicon-m-building-office-2')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/department/strings.infolist.updated_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/department/strings.infolist.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }
}
