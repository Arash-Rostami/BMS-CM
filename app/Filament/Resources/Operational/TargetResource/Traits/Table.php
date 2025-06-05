<?php

namespace App\Filament\Resources\Operational\TargetResource\Traits;


use App\Models\Target;
use App\Services\PersianCalendar;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Resources\Operational\TargetResource\Enums\Status as TargetStatus;

trait Table
{

    public static function showTargetable(): TextColumn
    {
        return TextColumn::make('targetable')
            ->label(__('resources/target/strings.table.targetable'))
            ->formatStateUsing(fn(Target $record) => $record->targetable_label)
            ->sortable(['targetable_type', 'targetable_id'])
            ->searchable(
                true,
                fn($query, string $search) => $query->SearchTargetable($search),
                false);
    }


    public static function showYear(): TextColumn
    {
        return TextColumn::make('year')
            ->label(__('resources/target/strings.table.year'))
            ->formatStateUsing(function (int $state): string {
                $calendar = app(PersianCalendar::class);

                return (string) $calendar->convertYear($state);
            })
            ->sortable();
    }

    public static function showStartFrom(): TextColumn
    {
        return TextColumn::make('start_from')
            ->label(__('resources/target/strings.table.start_from'))
            ->date()
            ->when(app()->isLocale('fa'), fn($column) => $column->jalaliDate())
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable();
    }

    public static function showEndIn(): TextColumn
    {
        return TextColumn::make('end_in')
            ->label(__('resources/target/strings.table.end_in'))
            ->date()
            ->when(app()->isLocale('fa'), fn($column) => $column->jalaliDate())
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable();
    }

    public static function showQuantity(): TextColumn
    {
        return TextColumn::make('quantity')
            ->label(__('resources/target/strings.table.quantity'))
            ->numeric()
            ->sortable();
    }

    public static function showAmount(): TextColumn
    {
        return TextColumn::make('amount')
            ->label(__('resources/target/strings.table.amount'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->numeric()
            ->sortable();
    }

    public static function showMetrics(): TextColumn
    {
        return TextColumn::make('metrics')
            ->label(__('resources/target/strings.table.metrics'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable()
            ->sortable();
    }

    public static function showStatus(): IconColumn
    {
        return IconColumn::make('status')
            ->label(__('resources/target/strings.table.status'))
            ->icon(fn(string $state): string => TargetStatus::tryFrom($state)?->getIcon() ?? '')
            ->color(fn(string $state): string => TargetStatus::tryFrom($state)?->getColor() ?? '');
    }

    public static function showAchievedQuantity(): TextColumn
    {
        return TextColumn::make('achieved_quantity')
            ->label(__('resources/target/strings.table.achieved_quantity'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->numeric()
            ->sortable();
    }

    public static function showAchievedAmount(): TextColumn
    {
        return TextColumn::make('achieved_amount')
            ->label(__('resources/target/strings.table.achieved_amount'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->numeric()
            ->sortable();
    }

    public static function showTags(): TextColumn
    {
        return TextColumn::make('tags')
            ->label(__('resources/target/strings.table.tags'))
            ->badge()
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable()
            ->color('info');
    }


    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/target/strings.table.creator'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/target/strings.table.updater'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/target/strings.table.created_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->dateTime()
            ->sortable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/target/strings.table.updated_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->dateTime()
            ->sortable();
    }
}
