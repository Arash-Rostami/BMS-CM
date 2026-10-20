<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Jobs\ExportCalendarHits;
use App\Models\CalendarHit;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarSeen;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarPresenter;
use App\Services\Calendar\Display\CalendarRange;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class CalendarDayWidget extends TableWidget
{
    private const URGENCY_STYLE = [
        'overdue' => ['color' => 'danger', 'icon' => 'heroicon-m-exclamation-triangle'],
        'today' => ['color' => 'warning', 'icon' => 'heroicon-m-clock'],
        'soon' => ['color' => 'info', 'icon' => 'heroicon-m-calendar-days'],
        'later' => ['color' => 'gray', 'icon' => 'heroicon-m-calendar'],
    ];

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 3];

    public mixed $date = null;

    public mixed $ruleId = null;

    public mixed $module = null;

    public mixed $anchor = null;

    protected ?CalendarPresenter $presenter = null;

    public function mount(): void
    {
        $this->date = CalendarRange::parseIso(request()->query('cal_date'))->toDateString();
        $this->anchor = $this->date;
        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), request()->query('cal_rule'), request()->query('cal_module'));
    }

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}

    #[On('calendar-day-selected')]
    public function syncSelection(mixed $date = null, mixed $ruleId = null, mixed $module = null, mixed $anchor = null): void
    {
        $this->date = CalendarRange::parseIso($date)->toDateString();
        $this->anchor = CalendarRange::parseIso($anchor ?? $this->date)->toDateString();
        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), $ruleId, $module);
        $this->deselectAllTableRecords();
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['date', 'ruleId', 'module', 'anchor'], true)) {
            $this->syncSelection($this->date, $this->ruleId, $this->module, $this->anchor);
        }
    }

    public function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->heading(__('resources/dashboard/strings.widgets.calendar.day.heading'))
            ->description(fn (): string => $this->description())
            ->filters($this->filterSet())
            ->filtersFormColumns(1)
            ->query(fn (): Builder => $this->scopedQuery())
            ->defaultGroup('subject_type')
            ->groupingDirectionSettingHidden()
            ->groups([$this->subjectGroup()])
            ->columns([
                Stack::make([
                    $this->showDate(),
                    $this->showLabel(),
                    $this->showRule(),
                    $this->showReason(),
                    $this->showStatus(),
                    $this->showSeen(),
                ])->space(2),
            ])
            ->contentGrid(['default' => 1])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (CalendarHit $record): ?string => $record->subject ? CalendarModules::url($record->subject) : null)
            ->recordClasses(fn (CalendarHit $record): ?string => $record->read_at !== null ? 'cal-day-read' : null)
            ->recordActions([$this->readAction()])
            ->groupedBulkActions([$this->exportBulkAction(), $this->bulkReadAction(true), $this->bulkReadAction(false)]))
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading(fn (): string => __('resources/dashboard/strings.widgets.calendar.day.empty_heading', ['date' => $this->isMonthScope() ? $this->monthRange()->label() : adaptiveDate($this->date)]))
            ->emptyStateDescription(fn (): string => $this->nextEventHint());
    }

    private function nextEventHint(): string
    {
        $from = $this->isMonthScope() ? $this->monthRange()->end : CalendarRange::parseIso($this->date);
        $next = CalendarBoard::nextEventDate($this->user(), $from, $this->ruleId, $this->module, $this->readMode());

        return $next
            ? __('resources/dashboard/strings.widgets.calendar.day.next', ['date' => adaptiveDate($next)])
            : __('resources/dashboard/strings.widgets.calendar.day.nothing_next');
    }

    private function scopedQuery(): Builder
    {
        return $this->isMonthScope()
            ? CalendarBoard::monthQuery($this->user(), $this->monthRange(), $this->ruleId, $this->module, $this->readMode())
            : CalendarBoard::dayQuery($this->user(), CalendarRange::parseIso($this->date), $this->ruleId, $this->module, $this->readMode());
    }

    private function monthRange(): CalendarRange
    {
        return CalendarRange::forMonth(CalendarRange::parseIso($this->anchor ?? $this->date)->toDateString(), isJalaliCalendar());
    }

    private function description(): string
    {
        if ($this->isMonthScope()) {
            return __('resources/dashboard/strings.widgets.calendar.day.month_description', ['month' => $this->monthRange()->label()]);
        }

        return $this->date ? adaptiveDate($this->date) : __('resources/dashboard/strings.widgets.calendar.day.none');
    }

    /**
     * @return array<int, SelectFilter>
     */
    private function filterSet(): array
    {
        return [
            $this->choiceFilter('scope', 'day', ['day', 'month']),
            $this->choiceFilter('read', 'unread', ['unread', 'read', 'all']),
        ];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function choiceFilter(string $name, string $default, array $values): SelectFilter
    {
        $label = __('resources/dashboard/strings.widgets.calendar.day.'.$name.'_label');
        $options = collect($values)->mapWithKeys(fn (string $value): array => [$value => __('resources/dashboard/strings.widgets.calendar.day.'.$name.'_'.$value)])->all();

        return SelectFilter::make($name)
            ->label($label)
            ->options($options)
            ->default($default)
            ->resetState(['value' => $default])
            ->selectablePlaceholder(false)
            ->native(false)
            ->query(fn (Builder $query): Builder => $query)
            ->indicateUsing(fn (array $state): array => is_string($state['value'] ?? null) && $state['value'] !== $default && isset($options[$state['value']]) ? [$label.': '.$options[$state['value']]] : []);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function choice(string $name, array $allowed, string $default): string
    {
        $value = is_array($this->tableFilters[$name] ?? null) ? ($this->tableFilters[$name]['value'] ?? null) : null;

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    private function isMonthScope(): bool
    {
        return $this->choice('scope', ['day', 'month'], 'day') === 'month';
    }

    private function readMode(): string
    {
        return $this->choice('read', ['unread', 'read', 'all'], 'unread');
    }

    private function seen(): CalendarSeen
    {
        return app(CalendarSeen::class);
    }

    private function readAction(): Action
    {
        return Action::make('toggleRead')
            ->iconButton()
            ->label(fn (CalendarHit $record): string => $this->readLabel($record->read_at !== null))
            ->tooltip(fn (CalendarHit $record): string => $this->readLabel($record->read_at !== null).' — '.__('resources/dashboard/strings.widgets.calendar.read.hint'))
            ->icon(fn (CalendarHit $record): string => $record->read_at !== null ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
            ->color('gray')
            ->action(fn (CalendarHit $record) => $this->setRead([$record], $record->read_at === null));
    }

    private function readLabel(bool $isRead): string
    {
        return __('resources/dashboard/strings.widgets.calendar.read.'.($isRead ? 'unmark' : 'mark'));
    }

    private function bulkReadAction(bool $read): BulkAction
    {
        return BulkAction::make($read ? 'markRead' : 'markUnread')
            ->label(__('resources/dashboard/strings.widgets.calendar.read.'.($read ? 'mark_selected' : 'unmark_selected')))
            ->icon($read ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->action(fn (Collection $records) => $this->setRead($records, $read))
            ->deselectRecordsAfterCompletion();
    }

    private function setRead(iterable $records, bool $read): void
    {
        $read ? $this->seen()->mark($this->user(), $records) : $this->seen()->unmark($this->user(), $records);

        $this->dispatch('calendar-read-changed');
    }

    private function showSeen(): TextColumn
    {
        return TextColumn::make('seen')
            ->state(fn (CalendarHit $record): ?string => $record->read_at
                ? __('resources/dashboard/strings.widgets.calendar.read.seen', ['when' => adaptiveDate($record->read_at, true)])
                : null)
            ->icon('heroicon-m-eye-slash')
            ->size('xs')
            ->color('gray');
    }

    private function subjectGroup(): Group
    {
        return Group::make('subject_type')
            ->label(__('resources/dashboard/strings.widgets.calendar.day.module'))
            ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw($this->subjectOrderSql($direction), $this->subjectOrder()))
            ->getTitleFromRecordUsing(fn (CalendarHit $record): string => CalendarModules::label($record->subject_type));
    }

    private function subjectOrderSql(string $direction): string
    {
        $slots = implode(', ', array_fill(0, count($this->subjectOrder()), '?'));

        return 'FIELD(calendar_hits.subject_type, '.$slots.') '.($direction === 'desc' ? 'desc' : 'asc');
    }

    /**
     * @return array<int, string>
     */
    private function subjectOrder(): array
    {
        return collect(CalendarModules::all())->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->keys()->all();
    }

    private function showDate(): TextColumn
    {
        return TextColumn::make('event_date')
            ->state(fn (CalendarHit $record): string => adaptiveDate($record->event_date))
            ->icon('heroicon-o-calendar-days')
            ->size('xs')
            ->color('gray')
            ->visible(fn (): bool => $this->isMonthScope());
    }

    private function showLabel(): TextColumn
    {
        return TextColumn::make('label')
            ->label(__('resources/dashboard/strings.widgets.calendar.day.item'))
            ->weight('semibold')
            ->wrap();
    }

    private function urgencyColor(CalendarHit $record): string
    {
        $urgency = $record->urgency();

        return $urgency === 'later' && $record->rule?->type === RuleType::ACTION ? 'success' : self::URGENCY_STYLE[$urgency]['color'];
    }

    private function showRule(): TextColumn
    {
        return TextColumn::make('rule.name')
            ->label(__('resources/dashboard/strings.widgets.calendar.day.rule'))
            ->badge()
            ->icon(fn (CalendarHit $record): string => self::URGENCY_STYLE[$record->urgency()]['icon'])
            ->tooltip(fn (CalendarHit $record): string => __('resources/dashboard/strings.widgets.calendar.urgency.'.$record->urgency()).' — '.__('resources/dashboard/strings.widgets.calendar.hints.'.($record->rule?->type === RuleType::ACTION ? 'action' : 'heads_up')))
            ->color(fn (CalendarHit $record): string => $this->urgencyColor($record));
    }

    private function showReason(): TextColumn
    {
        return TextColumn::make('reason')
            ->label(__('resources/dashboard/strings.widgets.calendar.day.rule'))
            ->state(fn (CalendarHit $record): string => $this->reasonFor($record)['text'])
            ->tooltip(fn (CalendarHit $record): string => $this->reasonFor($record)['full'])
            ->size('xs')
            ->color('gray')
            ->wrap();
    }

    /**
     * @return array{text: string, full: string}
     */
    private function reasonFor(CalendarHit $record): array
    {
        $this->presenter ??= app(CalendarPresenter::class);

        return $this->presenter->reason($record);
    }

    private function showStatus(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('resources/dashboard/strings.widgets.calendar.day.status'))
            ->html()
            ->state(fn (CalendarHit $record): string => $this->statusMarkup($record));
    }

    private function statusMarkup(CalendarHit $record): string
    {
        $name = e((string) ($record->subject?->status?->localizedName ?? ''));

        if (! $this->isOverdue($record)) {
            return $name ?: '-';
        }

        return $name.' <span class="tb-badge tb-danger" title="'.e(__('resources/dashboard/strings.widgets.calendar.hints.overdue')).'">'.e(__('resources/dashboard/strings.widgets.calendar.overdue')).'</span>';
    }

    private function isOverdue(CalendarHit $record): bool
    {
        return $record->event_date?->lt(today())
            && $record->rule?->type === RuleType::ACTION;
    }

    private function exportBulkAction(): BulkAction
    {
        return BulkAction::make('exportHits')
            ->label(__('resources/dashboard/strings.widgets.calendar.day.export'))
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (Collection $records): void {
                ExportCalendarHits::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private function user(): User
    {
        return auth()->user();
    }
}
