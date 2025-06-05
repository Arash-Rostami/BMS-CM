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

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('targetable')
                ->label('Target')
                ->state(fn(Target $record) => $record->targetable_label),
            ExportColumn::make('year'),
            ExportColumn::make('start_from'),
            ExportColumn::make('end_in'),
            ExportColumn::make('quantity'),
            ExportColumn::make('amount'),
            ExportColumn::make('achieved_quantity'),
            ExportColumn::make('achieved_amount'),
            ExportColumn::make('metrics'),
            ExportColumn::make('description'),
            ExportColumn::make('tags')->label('Tags')
                ->state(fn(Target $record): string => implode(', ', Arr::wrap($record->tags))
                ),
            ExportColumn::make('status'),
            ExportColumn::make('creator.name'),
            ExportColumn::make('updater.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }
}
