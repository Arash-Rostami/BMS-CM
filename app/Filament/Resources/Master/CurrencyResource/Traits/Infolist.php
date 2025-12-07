<?php

namespace App\Filament\Resources\Master\CurrencyResource\Traits;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('Created At'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/currency/strings.form.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewDescription(): TextEntry
    {
        return TextEntry::make('description')
            ->label(__('resources/currency/strings.form.description'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewEnglishName(): TextEntry
    {
        return TextEntry::make('english_name')
            ->label(__('resources/currency/strings.form.english_name'))
            ->icon('heroicon-m-language')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewIsActive(): IconEntry
    {
        return IconEntry::make('is_active')
            ->label(__('resources/currency/strings.form.is_active'))
            ->boolean()
            ->trueIcon('heroicon-m-check-circle')
            ->falseIcon('heroicon-m-x-circle')
            ->trueColor('success')
            ->falseColor('danger');
    }

    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/currency/strings.form.name'))
            ->icon('heroicon-m-banknotes')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('Updated At'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/currency/strings.form.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }
}
