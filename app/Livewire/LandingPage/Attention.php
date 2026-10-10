<?php

namespace App\Livewire\LandingPage;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Models\CalendarHit;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Sync\CalendarEngine;
use Livewire\Component;

class Attention extends Component
{
    public bool $isRtl = false;

    /**
     * @var array<int, array{label: string, module: string, rule: string, color: string, date: string, overdue: bool, url: ?string}>
     */
    public array $rows = [];

    public int $total = 0;

    public function mount(bool $isRtl): void
    {
        $user = auth()->user();
        $this->isRtl = $isRtl;
        $this->rows = $this->rowsFor($user);
        $this->total = CalendarBoard::attentionCount($user);
    }

    public function render()
    {
        return view('livewire.landing-page.attention', [
            'title' => __('resources/dashboard/strings.landing_page.attention.tab'),
            'countLabel' => __('resources/dashboard/strings.landing_page.attention.count_label'),
            'overdueLabel' => __('resources/dashboard/strings.widgets.calendar.overdue'),
            'emptyHint' => __('resources/dashboard/strings.landing_page.attention.empty'),
            'openCalendarLabel' => __('resources/dashboard/strings.landing_page.attention.open_calendar'),
            'dashboardUrl' => Dashboard::getUrl(),
            'moreLabel' => $this->total > count($this->rows)
                ? __('resources/dashboard/strings.landing_page.attention.more', ['count' => $this->total - count($this->rows)])
                : null,
        ]);
    }

    /**
     * @return array<int, array{label: string, module: string, rule: string, color: string, date: string, overdue: bool, url: ?string}>
     */
    private function rowsFor(User $user): array
    {
        return app(CalendarEngine::class)
            ->attention($user)
            ->map(fn (CalendarHit $hit): array => [
                'label' => $hit->label,
                'module' => CalendarModules::label($hit->subject_type),
                'rule' => (string) $hit->rule?->name,
                'color' => (string) ($hit->rule?->color?->value ?? 'slate'),
                'date' => adaptiveDate($hit->event_date),
                'overdue' => $hit->event_date->lt(today()) && $hit->rule?->type === RuleType::ACTION,
                'url' => $hit->subject ? CalendarModules::url($hit->subject) : null,
            ])
            ->all();
    }
}
