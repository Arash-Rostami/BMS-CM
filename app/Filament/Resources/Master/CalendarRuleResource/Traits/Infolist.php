<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Models\CalendarRule;
use App\Models\Role;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarPathResolver;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/calendarRule/strings.infolist.name'))
            ->badge()
            ->color(fn (CalendarRule $record): string => (string) ($record->color?->getColor() ?? 'gray'));
    }

    public static function viewSubject(): TextEntry
    {
        return TextEntry::make('subject')
            ->label(__('resources/calendarRule/strings.infolist.subject'))
            ->formatStateUsing(fn (string $state): string => CalendarModules::label($state));
    }

    public static function viewFilters(): TextEntry
    {
        return TextEntry::make('filters')
            ->label(__('resources/calendarRule/strings.infolist.filters'))
            ->getStateUsing(fn (CalendarRule $record): array => app(CalendarPathResolver::class)->summaries($record))
            ->listWithLineBreaks()
            ->placeholder('-');
    }

    public static function viewDatePath(): TextEntry
    {
        return TextEntry::make('date_path')
            ->label(__('resources/calendarRule/strings.infolist.date_path'))
            ->formatStateUsing(fn (string $state, CalendarRule $record): string => app(CalendarPathResolver::class)->datePathOptions($record->subject)[$state] ?? $state);
    }

    public static function viewDayShift(): TextEntry
    {
        return TextEntry::make('day_shift')
            ->label(__('resources/calendarRule/strings.infolist.day_shift'));
    }

    public static function viewLeadTimes(): TextEntry
    {
        return TextEntry::make('lead_times')
            ->label(__('resources/calendarRule/strings.infolist.lead_times'))
            ->getStateUsing(fn (CalendarRule $record): array => (array) ($record->lead_times ?? []))
            ->listWithLineBreaks()
            ->placeholder('-');
    }

    public static function viewOnDay(): IconEntry
    {
        return IconEntry::make('on_day')
            ->label(__('resources/calendarRule/strings.infolist.on_day'))
            ->boolean();
    }

    public static function viewType(): TextEntry
    {
        return TextEntry::make('type')
            ->label(__('resources/calendarRule/strings.infolist.type'))
            ->badge()
            ->icon(fn (CalendarRule $record): ?string => $record->type?->getIcon())
            ->formatStateUsing(fn (CalendarRule $record): string => (string) $record->type?->getLabel())
            ->color(fn (CalendarRule $record): string => (string) ($record->type?->getColor() ?? 'gray'));
    }

    public static function viewColor(): TextEntry
    {
        return TextEntry::make('color')
            ->label(__('resources/calendarRule/strings.infolist.color'))
            ->badge()
            ->formatStateUsing(fn (CalendarRule $record): string => (string) $record->color?->getLabel())
            ->color(fn (CalendarRule $record): string => (string) ($record->color?->getColor() ?? 'gray'));
    }

    public static function viewNotificationType(): TextEntry
    {
        return TextEntry::make('notification_type')
            ->label(__('resources/calendarRule/strings.infolist.notification_type'))
            ->badge()
            ->color('gray')
            ->getStateUsing(fn (CalendarRule $record): string => $record->notification_channel);
    }

    public static function viewVisibility(): TextEntry
    {
        return TextEntry::make('visibility')
            ->label(__('resources/calendarRule/strings.infolist.visibility'))
            ->badge()
            ->icon(fn (CalendarRule $record): ?string => $record->visibility?->getIcon())
            ->formatStateUsing(fn (CalendarRule $record): string => (string) $record->visibility?->getLabel())
            ->color(fn (CalendarRule $record): string => (string) ($record->visibility?->getColor() ?? 'gray'));
    }

    public static function viewSharedUserIds(): TextEntry
    {
        return TextEntry::make('shared_user_ids')
            ->label(__('resources/calendarRule/strings.infolist.shared_user_ids'))
            ->getStateUsing(fn (CalendarRule $record): array => User::query()
                ->whereIn('id', (array) ($record->shared_user_ids ?? []))
                ->pluck('name')
                ->all())
            ->listWithLineBreaks()
            ->placeholder('-');
    }

    public static function viewSharedRoleIds(): TextEntry
    {
        return TextEntry::make('shared_role_ids')
            ->label(__('resources/calendarRule/strings.infolist.shared_role_ids'))
            ->getStateUsing(fn (CalendarRule $record): array => Role::query()
                ->whereIn('id', $record->shared_role_ids ?? [])
                ->pluck('name')
                ->map(fn (string $name): string => UserRole::tryFrom($name)?->getLabel() ?? $name)
                ->all())
            ->listWithLineBreaks()
            ->placeholder('-');
    }

    public static function viewNotifyEmails(): TextEntry
    {
        return TextEntry::make('notify_emails')
            ->label(__('resources/calendarRule/strings.infolist.notify_emails'))
            ->getStateUsing(fn (CalendarRule $record): array => static::visibleEmails($record))
            ->listWithLineBreaks()
            ->placeholder('-');
    }

    public static function viewIsActive(): IconEntry
    {
        return IconEntry::make('is_active')
            ->label(__('resources/calendarRule/strings.infolist.is_active'))
            ->boolean();
    }

    public static function viewHitsCount(): TextEntry
    {
        return TextEntry::make('hits_count')
            ->label(__('resources/calendarRule/strings.infolist.hits_count'))
            ->badge()
            ->icon('heroicon-o-calendar-days')
            ->color(fn (CalendarRule $record): string => $record->hits_count === 0 ? 'gray' : 'success');
    }

    private static function visibleEmails(CalendarRule $record): array
    {
        $emails = $record->notify_emails ?? [];

        return $emails === [] || $record->isEditableBy(auth()->user())
            ? $emails
            : [__('resources/calendarRule/strings.infolist.notify_emails_count', ['count' => count($emails)])];
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/calendarRule/strings.infolist.created_by'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/calendarRule/strings.infolist.updated_by'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/calendarRule/strings.infolist.created_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/calendarRule/strings.infolist.updated_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }
}
