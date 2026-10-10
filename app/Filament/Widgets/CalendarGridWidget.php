<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\CalendarActivityAction;
use App\Models\User;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarPresenter;
use App\Services\Calendar\Display\CalendarRange;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Morilog\Jalali\Jalalian;

class CalendarGridWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 7];

    protected string $view = 'filament.widgets.calendar-grid';

    #[Url(as: 'cal_date')]
    public mixed $selected = null;

    #[Url(as: 'cal_rule')]
    public mixed $ruleId = null;

    #[Url(as: 'cal_module')]
    public mixed $module = null;

    public string $anchor = '';

    public mixed $jumpYear = null;

    public mixed $jumpMonth = null;

    public function mount(): void
    {
        $this->selected = CalendarRange::parseIso($this->selected)->toDateString();
        $this->anchor = $this->selected;

        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), $this->ruleId, $this->module);
    }

    public function selectDate(string $iso): void
    {
        $this->selected = CalendarRange::parseIso($iso)->toDateString();
        $this->anchor = $this->selected;
        $this->emitSelection();
    }

    public function prevMonth(): void
    {
        $this->shiftMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->shiftMonth(1);
    }

    public function goToToday(): void
    {
        $this->selectDate(today()->toDateString());
    }

    public function jump(): void
    {
        $this->jumpTo($this->jumpYear ?? $this->miniMonth['currentYear'], $this->jumpMonth ?? $this->miniMonth['currentMonth']);
    }

    public function updatedJumpYear(): void
    {
        $this->jumpYear = is_numeric($this->jumpYear) ? (int) $this->jumpYear : null;
    }

    public function updatedJumpMonth(): void
    {
        $this->jumpMonth = is_numeric($this->jumpMonth) ? max(1, min(12, (int) $this->jumpMonth)) : null;
    }

    public function updatedRuleId(): void
    {
        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), $this->ruleId, $this->module);
        $this->emitSelection();
    }

    public function toggleRule(int $ruleId): void
    {
        $this->ruleId = $this->ruleId === $ruleId ? null : $ruleId;
        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), $this->ruleId, $this->module);
        $this->emitSelection();
    }

    public function updatedModule(): void
    {
        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), $this->ruleId, $this->module);
        $this->emitSelection();
    }

    public function updatedSelected(): void
    {
        $this->selected = CalendarRange::parseIso($this->selected)->toDateString();
    }

    public function updatedAnchor(): void
    {
        $this->anchor = CalendarRange::parseIso($this->anchor)->toDateString();
    }

    public function calendarActivityAction(): Action
    {
        return CalendarActivityAction::make();
    }

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}

    #[Computed]
    public function jalali(): bool
    {
        return isJalaliCalendar();
    }

    #[Computed]
    public function range(): CalendarRange
    {
        return CalendarRange::forMonth($this->anchor, $this->jalali);
    }

    #[Computed]
    public function cells(): array
    {
        return $this->presenter()->groupedCells($this->range, $this->selected, CalendarBoard::monthGroups($this->user(), $this->range, $this->ruleId, $this->module));
    }

    #[Computed]
    public function agendaRows(): Collection
    {
        return CalendarBoard::agendaHits($this->user(), $this->range, $this->ruleId, $this->module);
    }

    #[Computed]
    public function agenda(): array
    {
        return $this->presenter()->agendaDays($this->range, $this->agendaRows);
    }

    #[Computed]
    public function legend(): Collection
    {
        return CalendarBoard::legendRules($this->user(), $this->module);
    }

    #[Computed]
    public function ruleOptions(): array
    {
        return CalendarBoard::ruleOptions($this->user());
    }

    #[Computed]
    public function moduleOptions(): array
    {
        return CalendarBoard::moduleOptions($this->user());
    }

    #[Computed]
    public function rangeLabel(): string
    {
        return $this->range->label();
    }

    #[Computed]
    public function weekdays(): array
    {
        return $this->presenter()->weekdayLabels($this->jalali);
    }

    #[Computed]
    public function miniMonth(): array
    {
        return $this->presenter()->miniMonth($this->anchor, $this->jalali);
    }

    #[Computed]
    public function agendaCapped(): bool
    {
        return $this->agendaRows->count() >= CalendarBoard::AGENDA_LIMIT;
    }

    #[Computed]
    public function offset(): int
    {
        return $this->range->weekdayOffset();
    }

    #[Computed]
    public function trailing(): int
    {
        return (7 - (($this->offset + count($this->cells)) % 7)) % 7;
    }

    private function shiftMonth(int $delta): void
    {
        $this->anchor = CalendarRange::shiftMonths($this->anchor, $delta, $this->jalali);
        $this->jumpYear = null;
        $this->jumpMonth = null;
    }

    private function jumpTo(int $year, int $month): void
    {
        $years = $this->miniMonth['years'];
        $year = max((int) min($years), min((int) max($years), $year));
        $month = max(1, min(12, $month));
        $this->anchor = $this->jalali
            ? (new Jalalian($year, $month, 1))->toCarbon()->toDateString()
            : Carbon::createFromDate($year, $month, 1)->toDateString();
        $this->jumpYear = null;
        $this->jumpMonth = null;
    }

    private function emitSelection(): void
    {
        $this->dispatch('calendar-day-selected', date: $this->selected, ruleId: $this->ruleId, module: $this->module);
    }

    private function user(): User
    {
        return auth()->user();
    }

    private function presenter(): CalendarPresenter
    {
        return app(CalendarPresenter::class);
    }
}
