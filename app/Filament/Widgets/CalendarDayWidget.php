<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Jobs\ExportCalendarHits;
use App\Models\CalendarHit;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarRange;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class CalendarDayWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 3];

    public ?string $date = null;

    public ?int $ruleId = null;

    public ?string $module = null;

    public function mount(): void
    {
        $this->date = CalendarRange::parseIso(request()->query('cal_date'))->toDateString();
        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), request()->query('cal_rule'), request()->query('cal_module'));
    }

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}

    #[On('calendar-day-selected')]
    public function syncSelection(mixed $date = null, mixed $ruleId = null, mixed $module = null): void
    {
        $this->date = CalendarRange::parseIso($date)->toDateString();
        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), $ruleId, $module);
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->heading(__('resources/dashboard/strings.widgets.calendar.day.heading'))
            ->description(fn (): string => $this->date ? adaptiveDate($this->date) : __('resources/dashboard/strings.widgets.calendar.day.none'))
            ->query(fn (): Builder => CalendarBoard::dayQuery($this->user(), CalendarRange::parseIso($this->date), $this->ruleId, $this->module))
            ->defaultGroup('subject_type')
            ->groups([$this->subjectGroup()])
            ->columns([
                $this->showLabel(),
                $this->showRule(),
                $this->showStatus(),
            ])
            ->recordUrl(fn (CalendarHit $record): ?string => $record->subject ? CalendarModules::url($record->subject) : null)
            ->bulkActions([$this->exportBulkAction()]));
    }

    private function subjectGroup(): Group
    {
        return Group::make('subject_type')
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

    private function showLabel(): TextColumn
    {
        return TextColumn::make('label')
            ->label(__('resources/dashboard/strings.widgets.calendar.day.item'))
            ->wrap();
    }

    private function showRule(): TextColumn
    {
        return TextColumn::make('rule.name')
            ->label(__('resources/dashboard/strings.widgets.calendar.day.rule'))
            ->badge()
            ->color(fn (CalendarHit $record): string => (string) ($record->rule?->color?->getColor() ?? 'gray'));
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

        return $name.' <span class="tb-badge tb-danger">'.e(__('resources/dashboard/strings.widgets.calendar.overdue')).'</span>';
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
