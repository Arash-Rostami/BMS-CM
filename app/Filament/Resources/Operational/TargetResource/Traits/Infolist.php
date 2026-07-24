<?php

namespace App\Filament\Resources\Operational\TargetResource\Traits;

use App\Filament\Resources\Operational\TargetResource\Enums\Status as TargetStatus;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewAchievedAmount(): TextEntry
    {
        return TextEntry::make('achieved_amount')
            ->label(__('resources/target/strings.infolist.achieved_amount'))
            ->formatStateUsing(fn ($state) => $state ? delimiter($state) : '-')
            ->icon('heroicon-m-currency-dollar')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewAchievedQuantity(): TextEntry
    {
        return TextEntry::make('achieved_quantity')
            ->label(__('resources/target/strings.infolist.achieved_quantity'))
            ->numeric()
            ->icon('heroicon-m-chart-pie')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewAmount(): TextEntry
    {
        return TextEntry::make('amount')
            ->label(__('resources/target/strings.infolist.amount'))
            ->formatStateUsing(fn ($state) => $state ? delimiter($state) : '-')
            ->icon('heroicon-m-currency-dollar')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/target/strings.infolist.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/target/strings.infolist.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewDescription(): TextEntry
    {
        return TextEntry::make('description')
            ->label(__('resources/target/strings.infolist.description'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewEndIn(): TextEntry
    {
        return TextEntry::make('end_in')
            ->label(__('resources/target/strings.infolist.end_in'))
            ->date()
            ->unless(app()->isLocale('en'), fn (TextEntry $column) => $column->jalaliDate())
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewMetrics(): TextEntry
    {
        return TextEntry::make('metrics')
            ->label(__('resources/target/strings.infolist.metrics'))
            ->badge()
            ->icon('heroicon-m-chart-bar')
            ->placeholder('-');
    }

    public static function viewQuantity(): TextEntry
    {
        return TextEntry::make('quantity')
            ->label(__('resources/target/strings.infolist.quantity'))
            ->numeric()
            ->icon('heroicon-m-cube')
            ->placeholder('-');
    }

    public static function viewStartFrom(): TextEntry
    {
        return TextEntry::make('start_from')
            ->label(__('resources/target/strings.infolist.start_from'))
            ->date()
            ->unless(app()->isLocale('en'), fn (TextEntry $column) => $column->jalaliDate())
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status')
            ->label(__('resources/target/strings.infolist.status'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => TargetStatus::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn (string $state): string => TargetStatus::tryFrom($state)?->getColor() ?? 'gray')
            ->placeholder('-');
    }

    public static function viewTagsJson(): TextEntry
    {
        return TextEntry::make('id')
            ->label(__('resources/target/strings.infolist.tags'))
            ->formatStateUsing(fn ($state, $record) => array_values(array_unique($record->tags ?? [])))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-tag')
            ->placeholder('-');
    }

    public static function viewTargetable(): TextEntry
    {
        return TextEntry::make('targetable')
            ->label(__('resources/target/strings.infolist.targetable'))
            ->formatStateUsing(fn ($state, $record = null): string => $record ? $record->targetable_label : '-')
            ->icon('heroicon-m-bullseye')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/target/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/target/strings.infolist.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewYear(): TextEntry
    {
        return TextEntry::make('year')
            ->label(__('resources/target/strings.infolist.year'))
            ->icon('heroicon-m-calendar')
            ->placeholder('-');
    }
}
