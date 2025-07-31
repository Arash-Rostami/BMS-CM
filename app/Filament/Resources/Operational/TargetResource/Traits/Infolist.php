<?php

namespace App\Filament\Resources\Operational\TargetResource\Traits;


use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\Operational\TargetResource\Enums\Status as TargetStatus;

trait Infolist
{
    public static function viewTargetable(): TextEntry
    {
        return TextEntry::make('targetable')
            ->label(__('resources/target/strings.form.targetable'))
            ->formatStateUsing(fn($state, $record = null): string => $record ? $record->targetable_label : '-');
    }

    public static function viewYear(): TextEntry
    {
        return TextEntry::make('year')
            ->label(__('resources/target/strings.form.year'));
    }

    public static function viewStartFrom(): TextEntry
    {
        return TextEntry::make('start_from')
            ->label(__('resources/target/strings.form.start_from'))
            ->date()
            ->unless(app()->isLocale('en'), fn(TextEntry $column) => $column->jalaliDate());
    }

    public static function viewEndIn(): TextEntry
    {
        return TextEntry::make('end_in')
            ->label(__('resources/target/strings.form.end_in'))
            ->date()
            ->unless(app()->isLocale('en'), fn(TextEntry $column) => $column->jalaliDate());

    }

    public static function viewQuantity(): TextEntry
    {
        return TextEntry::make('quantity')
            ->label(__('resources/target/strings.form.quantity'));
    }

    public static function viewAmount(): TextEntry
    {
        return TextEntry::make('amount')
            ->money()
            ->label(__('resources/target/strings.form.amount'));
    }

    public static function viewMetrics(): TextEntry
    {
        return TextEntry::make('metrics')
            ->label(__('resources/target/strings.form.metrics'));
    }

    public static function viewDescription(): TextEntry
    {
        return TextEntry::make('description')
            ->label(__('resources/target/strings.form.description'));
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status')
            ->label(__('resources/target/strings.form.status'))
            ->formatStateUsing(fn(string $state): string => TargetStatus::tryFrom($state)?->getLabel() ?? $state)
            ->badge()
            ->color(fn(string $state): string => TargetStatus::tryFrom($state)?->getColor() ?? 'gray');
    }

    public static function viewAchievedQuantity(): TextEntry
    {
        return TextEntry::make('achieved_quantity')
            ->label(__('resources/target/strings.form.achieved_quantity'));
    }

    public static function viewAchievedAmount(): TextEntry
    {
        return TextEntry::make('achieved_amount')
            ->label(__('resources/target/strings.form.achieved_amount'));
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/target/strings.form.creator'));
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/target/strings.form.updater'));
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/target/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/target/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewTagsJson(): TextEntry
    {
        return TextEntry::make('id')
            ->label(__('resources/target/strings.table.tags'))
            ->formatStateUsing(fn($state, $record) => implode(', ', array_values(array_unique($record->tags ?? []))))
            ->badge()
            ->color('info');
    }
}
