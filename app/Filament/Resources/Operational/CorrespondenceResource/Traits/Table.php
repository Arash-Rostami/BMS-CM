<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Traits;

use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use App\Models\Correspondence;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Table
{
    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/correspondence/strings.table.creator'))
            ->description(fn(Correspondence $record) => $record->created_at ? toPersianDate($record->created_at) : '-')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showFlags(): IconColumn
    {
        return IconColumn::make('flags')
            ->label(__('resources/correspondence/strings.table.visibility'))
            ->state(function (Correspondence $record): string {
                if ($record->is_private) return 'private';
                if ($record->is_internal) return 'internal';
                return 'public';
            })
            ->icon(fn(string $state): string => match ($state) {
                'private' => 'heroicon-s-lock-closed',
                'internal' => 'heroicon-s-building-office',
                default => 'heroicon-o-globe-alt',
            })
            ->color(fn(string $state): string => match ($state) {
                'private' => 'danger',
                'internal' => 'warning',
                default => 'gray',
            })
            ->tooltip(fn(string $state): string => match ($state) {
                'private' => __('resources/correspondence/strings.form.is_private'),
                'internal' => __('resources/correspondence/strings.form.is_internal'),
                default => 'Public',
            })
            ->toggleable();
    }

    public static function showLastUpdated(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/correspondence/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPriority(): TextColumn
    {
        return TextColumn::make('priority')
            ->label(__('resources/correspondence/strings.table.priority'))
            ->badge()
            ->sortable()
            ->searchable()
            ->formatStateUsing(fn(string $state): string => Priority::tryFrom($state)?->getLabel() ?? $state)
            ->icon(fn(string $state): ?string => Priority::tryFrom($state)?->getIcon())
            ->color(fn(string $state): string => Priority::tryFrom($state)?->getColor() ?? 'gray')
            ->toggleable();
    }

    public static function showRecipients(): TextColumn
    {
        return TextColumn::make('recipients.name')
            ->label(__('resources/correspondence/strings.table.recipients'))
            ->badge()
            ->separator(',')
            ->limitList(3)
            ->tooltip(fn(Correspondence $record) => $record->recipients->pluck('name')->implode(', '))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.name')
            ->label(__('resources/correspondence/strings.table.status'))
            ->badge()
            ->sortable()
            ->formatStateUsing(fn(Model $record): ?string => $record->status?->getLocalizedNameAttribute())
            ->color(fn(Model $record): string => match ($record->status?->english_name) {
                'Approved', 'Sent', 'Published' => 'success',
                'Draft', 'Pending' => 'gray',
                'Rejected', 'Archived' => 'danger',
                default => 'info'
            });
    }

    public static function showSubject(): TextColumn
    {
        return TextColumn::make('subject')
            ->label(__('resources/correspondence/strings.table.subject'))
            ->searchable()
            ->sortable()
            ->limit(50)
            ->weight(FontWeight::Bold)
            ->description(fn(Correspondence $record): string => Str::limit(strip_tags($record->body), 60));
    }

    public static function showType(): TextColumn
    {
        return TextColumn::make('type')
            ->label(__('resources/correspondence/strings.table.type'))
            ->badge()
            ->sortable()
            ->searchable()
            ->formatStateUsing(fn(string $state): string => Type::tryFrom($state)?->getLabel() ?? $state)
            ->icon(fn(string $state): ?string => Type::tryFrom($state)?->getIcon())
            ->color(fn(string $state): string => Type::tryFrom($state)?->getColor() ?? 'gray');
    }
}
