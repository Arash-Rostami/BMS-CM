<?php

namespace App\Filament\Resources\Operational\TargetResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Target;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Support\Arr;

class TargetExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Target::class;

    protected static function eagerLoadRelations(): array
    {
        return ['targetable'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('resources/target/strings.export.id')),
            ExportColumn::make('targetable')
                ->label(__('resources/target/strings.export.targetable'))
                ->state(fn (Target $record) => $record->targetable_label),
            ExportColumn::make('year')
                ->label(__('resources/target/strings.export.year')),
            ExportColumn::make('start_from')
                ->label(__('resources/target/strings.export.start_from')),
            ExportColumn::make('end_in')
                ->label(__('resources/target/strings.export.end_in')),
            ExportColumn::make('quantity')
                ->label(__('resources/target/strings.export.quantity')),
            ExportColumn::make('amount')
                ->label(__('resources/target/strings.export.amount')),
            ExportColumn::make('achieved_quantity')
                ->label(__('resources/target/strings.export.achieved_quantity')),
            ExportColumn::make('achieved_amount')
                ->label(__('resources/target/strings.export.achieved_amount')),
            ExportColumn::make('metrics')
                ->label(__('resources/target/strings.export.metrics')),
            ExportColumn::make('description')
                ->label(__('resources/target/strings.export.description')),
            ExportColumn::make('tags')
                ->label(__('resources/target/strings.export.tags'))
                ->state(fn (Target $record): string => implode(', ', Arr::wrap($record->tags))),
            ExportColumn::make('status')
                ->label(__('resources/target/strings.export.status')),
            ExportColumn::make('creator.name')
                ->label(__('resources/target/strings.export.creator')),
            ExportColumn::make('updater.name')
                ->label(__('resources/target/strings.export.updater')),
            ExportColumn::make('created_at')
                ->label(__('resources/target/strings.export.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('resources/target/strings.export.updated_at')),
        ];
    }
}
