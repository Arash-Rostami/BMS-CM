<?php

namespace App\Filament\Resources\Master\ActivityResource\Traits;

use App\Services\AuditChanges;
use App\Services\PermissionLabeler;
use Filament\Tables\Columns\TextColumn;
use Spatie\Activitylog\Models\Activity;

trait Table
{
    public static function showId(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/activity/strings.table.id'))
            ->badge()
            ->color('gray')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/activity/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable();
    }

    public static function showCauser(): TextColumn
    {
        return TextColumn::make('causer.name')
            ->label(__('resources/activity/strings.table.causer'))
            ->placeholder(__('resources/general/strings.audit.system'));
    }

    public static function showSubjectType(): TextColumn
    {
        return TextColumn::make('subject_type')
            ->label(__('resources/activity/strings.table.subject_type'))
            ->formatStateUsing(fn (?string $state): string => $state ? PermissionLabeler::getEntityLabel($state) : '-')
            ->badge()
            ->color('gray')
            ->sortable();
    }

    public static function showRecord(): TextColumn
    {
        return TextColumn::make('subject_id')
            ->label(__('resources/activity/strings.table.subject_id'))
            ->state(fn (Activity $record): string => app(AuditChanges::class)->record($record))
            ->url(fn (Activity $record): ?string => app(AuditChanges::class)->url($record))
            ->badge()
            ->color('info');
    }

    public static function showEvent(): TextColumn
    {
        return TextColumn::make('event')
            ->label(__('resources/activity/strings.table.event'))
            ->formatStateUsing(fn (?string $state): string => static::eventLabels()[$state] ?? (string) $state)
            ->badge()
            ->color(fn (?string $state): string => static::eventColor($state))
            ->sortable();
    }

    public static function showChanges(): TextColumn
    {
        return TextColumn::make('changes')
            ->label(__('resources/activity/strings.table.changes'))
            ->state(fn (Activity $record): string => app(AuditChanges::class)->summary($record))
            ->placeholder('-')
            ->limit(60)
            ->tooltip(fn (Activity $record): ?string => filled($summary = app(AuditChanges::class)->summary($record)) ? $summary : null);
    }

    public static function eventColor(?string $event): string
    {
        return match ($event) {
            'created' => 'success',
            'deleted' => 'danger',
            'restored' => 'info',
            default => 'warning',
        };
    }
}
