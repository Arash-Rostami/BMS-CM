<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Traits;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Jobs\ExportCalendarRules;
use App\Models\CalendarRule;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarPathResolver;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait Table
{
    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportRules')
            ->label(__('resources/calendarRule/strings.export.export_rules'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => static::canViewAny())
            ->action(function (Collection $records): void {
                ExportCalendarRules::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getActivateBulkAction(): BulkAction
    {
        return BulkAction::make('activate')
            ->authorize(fn (): bool => static::canEditAny())
            ->label(__('resources/general/strings.bulk.activate.label'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->action(fn (Collection $records): mixed => static::toggleActiveBulkAction($records, true))
            ->deselectRecordsAfterCompletion();
    }

    public static function getDeactivateBulkAction(): BulkAction
    {
        return BulkAction::make('deactivate')
            ->authorize(fn (): bool => static::canEditAny())
            ->label(__('resources/general/strings.bulk.deactivate.label'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->action(fn (Collection $records): mixed => static::toggleActiveBulkAction($records, false))
            ->deselectRecordsAfterCompletion();
    }

    public static function getEditAction(): EditAction
    {
        return EditAction::make()
            ->modalWidth('4xl')
            ->mutateRecordDataUsing(fn (array $data): array => [
                ...$data,
                'extra_paths' => app(CalendarPathResolver::class)->relationPathsFor((string) $data['subject'], $data['extra_paths'] ?? [], $data['filters']['rules'] ?? []),
                'filters' => (array) ($data['filters']['rules'] ?? []),
            ])
            ->mutateFormDataUsing(fn (array $data, CalendarRule $record): array => static::assertNotDuplicate($data, $record->id));
    }

    public static function getSeeOnCalendarAction(): Action
    {
        return Action::make('seeOnCalendar')
            ->label(__('resources/calendarRule/strings.actions.see_on_calendar'))
            ->icon('heroicon-o-calendar-days')
            ->url(fn (CalendarRule $record): string => Dashboard::getUrl(['cal_rule' => $record->id]));
    }

    public static function getDuplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label(__('resources/calendarRule/strings.actions.duplicate'))
            ->icon('heroicon-o-document-duplicate')
            ->visible(fn (CalendarRule $record): bool => static::canCreate())
            ->modalHeading(__('resources/calendarRule/strings.actions.duplicate'))
            ->schema(fn (Schema $schema): Schema => static::form($schema))
            ->fillForm(fn (CalendarRule $record): array => static::duplicateFormData($record))
            ->action(function (CalendarRule $record, array $data, Action $action): void {
                if (static::duplicateExists($data, $record->id)) {
                    Notification::make()
                        ->title(__('resources/calendarRule/strings.actions.duplicate_exists'))
                        ->danger()
                        ->send();

                    $action->halt();
                }

                static::getModel()::create($data);
            })
            ->successNotificationTitle(__('resources/calendarRule/strings.actions.duplicated'));
    }

    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/calendarRule/strings.table.name'))
            ->badge()
            ->color(fn (CalendarRule $record): string => (string) ($record->color?->getColor() ?? 'gray'))
            ->searchable()
            ->sortable();
    }

    public static function showSubject(): TextColumn
    {
        return TextColumn::make('subject')
            ->label(__('resources/calendarRule/strings.table.subject'))
            ->formatStateUsing(fn (string $state): string => CalendarModules::label($state))
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->orWhereIn(
                'subject',
                array_values(array_filter(array_keys(CalendarModules::all()), fn (string $module): bool => mb_stripos(CalendarModules::label($module), $search) !== false))
            ))
            ->sortable();
    }

    public static function showWatches(): TextColumn
    {
        return TextColumn::make('watches')
            ->label(__('resources/calendarRule/strings.table.watches'))
            ->getStateUsing(fn (CalendarRule $record): array => static::watchesLines($record))
            ->badge()
            ->listWithLineBreaks()
            ->limit(50)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showType(): TextColumn
    {
        return TextColumn::make('type')
            ->label(__('resources/calendarRule/strings.table.type'))
            ->badge()
            ->icon(fn (CalendarRule $record): ?string => $record->type?->getIcon())
            ->formatStateUsing(fn (CalendarRule $record): string => (string) $record->type?->getLabel())
            ->color(fn (CalendarRule $record): string => (string) ($record->type?->getColor() ?? 'gray'))
            ->sortable();
    }

    public static function showNotificationType(): TextColumn
    {
        return TextColumn::make('notification_type')
            ->label(__('resources/calendarRule/strings.table.notification_type'))
            ->badge()
            ->color('gray')
            ->getStateUsing(fn (CalendarRule $record): string => $record->notification_channel)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->orWhereIn(
                'notification_type',
                array_keys(array_filter(CalendarRule::notificationChannel(), fn (string $label): bool => mb_stripos($label, $search) !== false))
            ))
            ->sortable()
            ->toggleable();
    }

    public static function showVisibility(): TextColumn
    {
        return TextColumn::make('visibility')
            ->label(__('resources/calendarRule/strings.table.visibility'))
            ->badge()
            ->icon(fn (CalendarRule $record): ?string => $record->visibility?->getIcon())
            ->formatStateUsing(fn (CalendarRule $record): string => (string) $record->visibility?->getLabel())
            ->color(fn (CalendarRule $record): string => (string) ($record->visibility?->getColor() ?? 'gray'))
            ->sortable();
    }

    public static function showHitsCount(): TextColumn
    {
        return TextColumn::make('hits_count')
            ->label(__('resources/calendarRule/strings.table.hits_count'))
            ->icon('heroicon-o-calendar-days')
            ->alignEnd()
            ->badge()
            ->color(fn (?int $state): string => $state === 0 ? 'gray' : 'success')
            ->sortable();
    }

    public static function showNextDue(): TextColumn
    {
        return TextColumn::make('hits_min_event_date')
            ->label(__('resources/calendarRule/strings.table.next_due'))
            ->adaptiveDate()
            ->color(fn (CalendarRule $record): ?string => static::nextDueColor($record->hits_min_event_date))
            ->sortable();
    }

    public static function showIsActive(): ToggleColumn
    {
        return ToggleColumn::make('is_active')
            ->label(__('resources/calendarRule/strings.table.is_active'))
            ->disabled(fn (CalendarRule $record): bool => ! static::canEdit($record))
            ->sortable();
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/calendarRule/strings.table.created_by'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/calendarRule/strings.table.updated_by'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/calendarRule/strings.table.created_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->adaptiveDateTime()
            ->sortable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/calendarRule/strings.table.updated_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->adaptiveDateTime()
            ->sortable();
    }

    private static function nextDueColor(?string $date): ?string
    {
        return match (true) {
            $date === null => null,
            $date < today()->toDateString() => 'danger',
            $date <= today()->addDays(7)->toDateString() => 'warning',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private static function watchesLines(CalendarRule $record): array
    {
        $resolver = app(CalendarPathResolver::class);

        return [
            $resolver->datePathOptions($record->subject)[$record->date_path] ?? $record->date_path,
            ...$resolver->summaries($record),
        ];
    }

    private static function toggleActiveBulkAction(Collection $records, bool $active): void
    {
        $skipped = 0;

        foreach ($records as $record) {
            if (! $record->isEditableBy(auth()->user())) {
                $skipped++;

                continue;
            }

            $record->update(['is_active' => $active]);
        }

        Notification::make()
            ->title(__($active
                ? 'resources/general/strings.bulk.activate.notification'
                : 'resources/general/strings.bulk.deactivate.notification'))
            ->success()
            ->send();

        if ($skipped > 0) {
            Notification::make()
                ->title(__('resources/calendarRule/strings.bulk.skipped', ['count' => $skipped]))
                ->warning()
                ->send();
        }
    }

    private static function duplicateFormData(CalendarRule $record): array
    {
        return [
            'name' => $record->name.__('resources/calendarRule/strings.general.copy_suffix'),
            'subject' => $record->subject,
            'filters' => (array) ($record->filters['rules'] ?? []),
            'extra_paths' => app(CalendarPathResolver::class)->relationPathsFor($record->subject, $record->extra_paths, $record->filters['rules'] ?? []),
            'date_path' => $record->date_path,
            'day_shift' => $record->day_shift,
            'lead_times' => $record->lead_times,
            'on_day' => $record->on_day,
            'notification_type' => $record->notification_type,
            'type' => $record->type?->value,
            'color' => $record->color?->value,
            'visibility' => $record->visibility === Visibility::ROLES ? Visibility::ROLES->value : Visibility::ME->value,
            'shared_role_ids' => $record->shared_role_ids,
            'notify_emails' => $record->isEditableBy(auth()->user()) ? $record->notify_emails : null,
            'is_active' => $record->is_active,
        ];
    }
}
