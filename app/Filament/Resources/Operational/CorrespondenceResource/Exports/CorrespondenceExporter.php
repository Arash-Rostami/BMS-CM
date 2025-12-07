<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Exports;

use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use App\Filament\Traits\ExportDefaults;
use App\Models\Correspondence;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class CorrespondenceExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Correspondence::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('subject'),
            ExportColumn::make('type')
                ->formatStateUsing(fn(string $state): string => Type::tryFrom($state)?->getLabel() ?? $state),
            ExportColumn::make('priority')
                ->formatStateUsing(fn(string $state): string => Priority::tryFrom($state)?->getLabel() ?? $state),
            ExportColumn::make('status.name'),
            ExportColumn::make('is_internal')
                ->formatStateUsing(fn(bool $state) => $state ? __('resources/correspondence/strings.form.is_internal') : '-'),
            ExportColumn::make('is_private')
                ->formatStateUsing(fn(bool $state) => $state ? __('resources/correspondence/strings.form.is_private') : '-'),

            ExportColumn::make('body')
                ->formatStateUsing(fn(string $state) => strip_tags($state)),

            ExportColumn::make('recipients')
                ->label('Recipients')
                ->state(function (Correspondence $record) {
                    return $record->recipients->map(fn($user) => "{$user->name} (" . ($user->pivot->type === 'to' ? 'To' : 'CC') . ")"
                    )->implode(', ');
                }),

            ExportColumn::make('creator.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "Correspondences-{$export->getKey()}";
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['status', 'creator', 'recipients']);
    }
}
