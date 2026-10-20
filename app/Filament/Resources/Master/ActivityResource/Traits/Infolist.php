<?php

namespace App\Filament\Resources\Master\ActivityResource\Traits;

use App\Services\AuditChanges;
use App\Services\PermissionLabeler;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Spatie\Activitylog\Models\Activity;

trait Infolist
{
    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/activity/strings.infolist.created_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->icon('heroicon-m-clock');
    }

    public static function viewCauser(): TextEntry
    {
        return TextEntry::make('causer.name')
            ->label(__('resources/activity/strings.infolist.causer'))
            ->icon('heroicon-m-user-circle')
            ->placeholder(__('resources/general/strings.audit.system'));
    }

    public static function viewSubjectType(): TextEntry
    {
        return TextEntry::make('subject_type')
            ->label(__('resources/activity/strings.infolist.subject_type'))
            ->formatStateUsing(fn (?string $state): string => $state ? PermissionLabeler::getEntityLabel($state) : '-')
            ->badge()
            ->color('gray')
            ->icon('heroicon-m-cube');
    }

    public static function viewRecord(): TextEntry
    {
        return TextEntry::make('subject_id')
            ->label(__('resources/activity/strings.infolist.subject_id'))
            ->state(fn (Activity $record): string => app(AuditChanges::class)->record($record))
            ->url(fn (Activity $record): ?string => app(AuditChanges::class)->url($record))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-hashtag');
    }

    public static function viewEvent(): TextEntry
    {
        return TextEntry::make('event')
            ->label(__('resources/activity/strings.infolist.event'))
            ->formatStateUsing(fn (?string $state): string => static::eventLabels()[$state] ?? (string) $state)
            ->badge()
            ->color(fn (?string $state): string => static::eventColor($state));
    }

    public static function viewChanges(): RepeatableEntry
    {
        return RepeatableEntry::make('changes')
            ->label(__('resources/activity/strings.infolist.changes'))
            ->state(fn (Activity $record): array => app(AuditChanges::class)->fields(collect([$record]))[$record->id] ?? [])
            ->schema([
                TextEntry::make('label')->label(__('resources/activity/strings.infolist.field'))->weight('bold'),
                TextEntry::make('old')->label(__('resources/activity/strings.infolist.old'))->color('gray'),
                TextEntry::make('new')->label(__('resources/activity/strings.infolist.new'))->color('success'),
            ])
            ->columns(3)
            ->columnSpanFull();
    }
}
