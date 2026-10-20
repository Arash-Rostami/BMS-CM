<?php

namespace App\Livewire\LandingPage;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Models\CalendarHit;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarSeen;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarPresenter;
use App\Services\Calendar\Display\CalendarRange;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class Attention extends Component
{
    use WithoutUrlPagination, WithPagination;

    public bool $isRtl = false;

    public const PER_PAGE = 8;

    public string $module = '';

    public bool $unread = true;

    public bool $hiddenOnly = false;

    public string $time = '';

    /**
     * @var array<int, array{module: string, label: string, count: int}>
     */
    #[Locked]
    public array $modules = [];

    #[Locked]
    public int $total = 0;

    public string $month = '';

    public string $monthLabel = '';

    public bool $isCurrentMonth = true;

    public function mount(bool $isRtl): void
    {
        $this->isRtl = $isRtl;
        $this->load();
    }

    public function prevMonth(): void
    {
        $this->shift(-1);
    }

    public function nextMonth(): void
    {
        $this->shift(1);
    }

    public function goToCurrentMonth(): void
    {
        $this->month = '';
        $this->changed();
    }

    public function updatedMonth(): void
    {
        $this->changed();
    }

    public function filterModule(string $module): void
    {
        $known = in_array($module, array_column($this->modules, 'module'), true);

        $this->module = $known && $this->module !== $module ? $module : '';
        $this->resetPage();
    }

    public function setTime(string $time): void
    {
        $this->time = in_array($time, ['previous', 'upcoming'], true) && $this->time !== $time ? $time : '';
        $this->changed();
    }

    public function toggleUnread(): void
    {
        $this->unread = $this->hiddenOnly || ! $this->unread;
        $this->hiddenOnly = false;
        $this->changed();
    }

    public function showHidden(): void
    {
        $this->hiddenOnly = ! $this->hiddenOnly;
        $this->unread = ! $this->hiddenOnly;
        $this->changed();
    }

    public function showAll(): void
    {
        $this->unread = false;
        $this->hiddenOnly = false;
        $this->time = '';
        $this->changed();
    }

    public function markRead(int $id): void
    {
        $this->setRead([$id], true);
    }

    public function markUnread(int $id): void
    {
        $this->setRead([$id], false);
    }

    public function markPageRead(): void
    {
        $this->setRead($this->paginator()->getCollection()->where('read', false)->pluck('id')->all(), true);
    }

    public function render()
    {
        $paginator = $this->paginator();

        return view('livewire.landing-page.attention', [
            'paginator' => $paginator,
            'title' => __('resources/dashboard/strings.landing_page.attention.tab'),
            'countLabel' => __('resources/dashboard/strings.landing_page.attention.count_label'),
            'overdueLabel' => __('resources/dashboard/strings.widgets.calendar.overdue'),
            'emptyHint' => __('resources/dashboard/strings.landing_page.attention.'.($this->time === '' && ! $this->hiddenOnly ? 'empty' : 'empty_filtered'), ['month' => $this->monthLabel]),
            'pageUnread' => $paginator->getCollection()->contains('read', false),
            'hint' => __('resources/dashboard/strings.widgets.calendar.read.hint'),
            'prevLabel' => __('resources/dashboard/strings.landing_page.attention.previous_month'),
            'nextLabel' => __('resources/dashboard/strings.landing_page.attention.next_month'),
            'currentLabel' => __('resources/dashboard/strings.landing_page.attention.current_month'),
            'openCalendarLabel' => __('resources/dashboard/strings.landing_page.attention.open_calendar'),
            'dashboardUrl' => Dashboard::getUrl(),
            'tierLabels' => [
                0 => __('resources/dashboard/strings.landing_page.attention.tier_overdue'),
                1 => __('resources/dashboard/strings.landing_page.attention.tier_today'),
                2 => __('resources/dashboard/strings.landing_page.attention.tier_upcoming'),
            ],
        ]);
    }

    private function paginator(): LengthAwarePaginator
    {
        $user = auth()->user();
        $presenter = app(CalendarPresenter::class);
        $total = $this->selectedTotal();

        return CalendarBoard::attentionPage(
            $user,
            $this->range(),
            $this->readMode(),
            $this->timeFilter(),
            $this->activeModule() === '' ? null : $this->activeModule(),
            self::PER_PAGE,
            max(1, min($this->getPage(), $this->lastPage())),
            $total
        )->through(fn (CalendarHit $hit): array => $this->rowFor($hit, $presenter));
    }

    private function activeModule(): string
    {
        return in_array($this->module, array_column($this->modules, 'module'), true) ? $this->module : '';
    }

    private function selectedTotal(): int
    {
        return $this->activeModule() === '' ? $this->total : (int) (collect($this->modules)->firstWhere('module', $this->module)['count'] ?? 0);
    }

    private function setRead(array $ids, bool $read): void
    {
        $user = auth()->user();
        $seen = app(CalendarSeen::class);

        $read ? $seen->mark($user, $ids) : $seen->unmark($user, $ids);

        $this->load();
        $this->setPage(min($this->getPage(), $this->lastPage()));
        $this->dispatch('calendar-read-changed');
    }

    private function lastPage(): int
    {
        return max(1, (int) ceil($this->selectedTotal() / self::PER_PAGE));
    }

    private function readMode(): string
    {
        return $this->hiddenOnly ? 'read' : ($this->unread ? 'unread' : 'all');
    }

    private function timeFilter(): ?string
    {
        return $this->time === '' ? null : $this->time;
    }

    private function shift(int $delta): void
    {
        $this->month = CalendarRange::shiftMonths($this->range()->anchor, $delta, isJalaliCalendar());
        $this->changed();
    }

    private function changed(): void
    {
        $this->resetPage();
        $this->load();
    }

    private function range(): CalendarRange
    {
        return CalendarRange::forMonth(CalendarRange::parseIso($this->month)->toDateString(), isJalaliCalendar());
    }

    private function load(): void
    {
        $user = auth()->user();
        $range = $this->range();
        $this->month = $range->start->toDateString();
        $this->monthLabel = $range->label();
        $this->isCurrentMonth = today()->betweenIncluded($range->start, $range->end);
        $counts = CalendarBoard::attentionModules($user, $range, $this->readMode(), $this->timeFilter());
        $this->modules = $this->modulesFor($counts);
        $this->total = array_sum($counts);

        if ($this->module !== '' && ! isset($counts[$this->module])) {
            $this->module = '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFor(CalendarHit $hit, CalendarPresenter $presenter): array
    {
        $isAction = $hit->rule?->type === RuleType::ACTION;
        $overdue = $hit->event_date->lt(today()) && $isAction;
        $reason = $presenter->reason($hit);

        return [
            'id' => $hit->id,
            'read' => $hit->read_at !== null,
            'seen' => $hit->read_at ? __('resources/dashboard/strings.landing_page.attention.seen', ['when' => adaptiveDate($hit->read_at, true)]) : '',
            'label' => $hit->label,
            'module' => CalendarModules::label($hit->subject_type),
            'rule' => (string) $hit->rule?->name,
            'color' => (string) ($hit->rule?->color?->value ?? 'slate'),
            'date' => adaptiveDate($hit->event_date),
            'reason' => $reason['text'],
            'reason_full' => $reason['full'],
            'tier' => $overdue ? 0 : ($hit->event_date->isSameDay(today()) ? 1 : 2),
            'overdue' => $overdue,
            'url' => $hit->subject ? CalendarModules::url($hit->subject) : null,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array{module: string, label: string, count: int}>
     */
    private function modulesFor(array $counts): array
    {
        return collect($counts)
            ->map(fn (int $count, string $module): array => ['module' => $module, 'label' => CalendarModules::label($module), 'count' => $count])
            ->sortBy('label')
            ->values()
            ->all();
    }
}
