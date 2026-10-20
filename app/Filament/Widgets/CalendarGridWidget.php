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
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

class CalendarGridWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use WithoutUrlPagination;
    use WithPagination;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 7];

    protected string $view = 'filament.widgets.calendar-grid';

    #[Url(as: 'cal_date')]
    public mixed $selected = null;

    #[Url(as: 'cal_rule')]
    public mixed $ruleId = null;

    #[Url(as: 'cal_module')]
    public mixed $module = null;

    public const LEGEND_PER_PAGE = 12;

    public string $anchor = '';

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

    public function jump(mixed $year, mixed $month): void
    {
        $mini = $this->presenter()->miniMonth($this->anchor, $this->jalali);

        $this->jumpTo(
            is_numeric($year) ? (int) $year : $mini['currentYear'],
            is_numeric($month) ? (int) $month : $mini['currentMonth'],
            $mini['years'],
        );
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
        [$this->ruleId, $this->module] = CalendarBoard::sanitize($this->user(), $this->ruleId, $this->module, moduleWins: true);
        $this->resetPage('legendPage');
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

    #[On('calendar-read-changed')]
    public function calendarReadChanged(): void {}

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
    public function legend(): LengthAwarePaginator
    {
        $page = max(1, min($this->getPage('legendPage'), (int) ceil($this->legendTotal / self::LEGEND_PER_PAGE)));

        return CalendarBoard::legendPage($this->user(), $this->module, self::LEGEND_PER_PAGE, $page, $this->legendTotal, is_numeric($this->ruleId) ? (int) $this->ruleId : null);
    }

    #[Computed]
    public function legendTotal(): int
    {
        return CalendarBoard::legendCount($this->user(), $this->module);
    }

    #[Computed]
    public function ruleDetails(): ?array
    {
        $details = CalendarBoard::ruleDetails($this->user(), is_numeric($this->ruleId) ? (int) $this->ruleId : null);

        if ($details === null) {
            return null;
        }

        $details['changes'] = $details['changes']->map(fn (array $row): array => CalendarActivityAction::describe($row))
            ->filter(fn (array $row): bool => $row['text'] !== '')
            ->values()
            ->all();

        return $details;
    }

    #[Computed]
    public function ruleOptions(): array
    {
        return CalendarBoard::ruleOptions($this->user(), is_numeric($this->ruleId) ? (int) $this->ruleId : null);
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
        $this->emitSelection();
    }

    private function jumpTo(int $year, int $month, array $years): void
    {
        $year = max((int) min($years), min((int) max($years), $year));
        $month = max(1, min(12, $month));
        $this->anchor = $this->jalali
            ? (new Jalalian($year, $month, 1))->toCarbon()->toDateString()
            : Carbon::createFromDate($year, $month, 1)->toDateString();
        $this->emitSelection();
    }

    private function emitSelection(): void
    {
        $this->dispatch('calendar-day-selected', date: $this->selected, ruleId: $this->ruleId, module: $this->module, anchor: $this->anchor);
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
