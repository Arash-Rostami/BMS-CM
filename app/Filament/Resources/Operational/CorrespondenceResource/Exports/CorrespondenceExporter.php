<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Exports;

use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use App\Filament\Traits\ExportDefaults;
use App\Models\Correspondence;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class CorrespondenceExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Correspondence::class;

    protected static function eagerLoadRelations(): array
    {
        return ['status', 'recipients'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/correspondence/strings.export.id')),
            ExportColumn::make('subject')->label(__('resources/correspondence/strings.export.subject')),
            ExportColumn::make('type')->label(__('resources/correspondence/strings.export.type'))
                ->formatStateUsing(fn (string $state): string => Type::tryFrom($state)?->getLabel() ?? $state),
            ExportColumn::make('priority')->label(__('resources/correspondence/strings.export.priority'))
                ->formatStateUsing(fn (string $state): string => Priority::tryFrom($state)?->getLabel() ?? $state),
            ExportColumn::make('status.name')->label(__('resources/correspondence/strings.export.status')),
            ExportColumn::make('is_internal')->label(__('resources/correspondence/strings.export.is_internal'))
                ->formatStateUsing(fn (bool $state) => $state ? __('resources/correspondence/strings.form.is_internal') : '-'),
            ExportColumn::make('is_private')->label(__('resources/correspondence/strings.export.is_private'))
                ->formatStateUsing(fn (bool $state) => $state ? __('resources/correspondence/strings.form.is_private') : '-'),

            ExportColumn::make('body')->label(__('resources/correspondence/strings.export.body'))
                ->formatStateUsing(fn (string $state) => strip_tags($state)),

            ExportColumn::make('recipients')
                ->label(__('resources/correspondence/strings.export.recipients'))
                ->state(function (Correspondence $record) {
                    $to = __('resources/correspondence/strings.export.recipient_to');
                    $cc = __('resources/correspondence/strings.export.recipient_cc');

                    return $record->recipients->map(fn ($user) => "{$user->name} (".($user->pivot->type === 'to' ? $to : $cc).')'
                    )->implode(', ');
                }),

            ExportColumn::make('creator.name')->label(__('resources/correspondence/strings.export.creator')),
            ExportColumn::make('created_at')->label(__('resources/correspondence/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/correspondence/strings.export.updated_at')),
        ];
    }
}
