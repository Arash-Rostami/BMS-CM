<?php

namespace App\Services\Calendar\Display;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Services\Calendar\CalendarModules;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

class CalendarPresenter
{
    /**
     * @return array<int, array{iso: string, day: int, isToday: bool, isPast: bool, isSelected: bool, count: int, colors: array<int, string>, hasOverdue: bool}>
     */
    public function monthCells(CalendarRange $range, string $selectedIso, Collection $hits): array
    {
        $today = today()->toDateString();
        $byDay = $this->withRules($hits)->groupBy(fn ($hit): string => $hit->event_date->toDateString());

        return collect($range->days())
            ->map(fn (Carbon $date): array => $this->monthCell($range, $date, $selectedIso, $today, $byDay->get($date->toDateString(), collect())))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object{event_date: Carbon, rule_color: string, rule_type: string, hit_count: int}>  $grouped
     * @return array<int, array{iso: string, day: int, isToday: bool, isPast: bool, isSelected: bool, count: int, colors: array<int, string>, hasOverdue: bool}>
     */
    public function groupedCells(CalendarRange $range, string $selectedIso, Collection $grouped): array
    {
        $today = today()->toDateString();
        $byDay = $grouped->groupBy(fn ($row): string => $row->event_date->toDateString());

        return collect($range->days())
            ->map(fn (Carbon $date): array => $this->groupedCell($range, $date, $selectedIso, $today, $byDay->get($date->toDateString(), collect())))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{iso: string, label: string, hits: array<int, array<string, mixed>>}>
     */
    public function agendaDays(CalendarRange $range, Collection $hits): array
    {
        return $this->withRules($hits, ['rule', 'subject'])
            ->groupBy(fn ($hit): string => $hit->event_date->toDateString())
            ->sortKeys()
            ->map(fn (Collection $dayHits, string $iso): array => [
                'iso' => $iso,
                'label' => $this->dayLabel($range, $iso),
                'hits' => $this->overdueFirst($dayHits, $iso < today()->toDateString()),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function weekdayLabels(bool $jalali): array
    {
        return array_map(
            fn (int $offset): string => $this->weekdayLabel($jalali, Carbon::parse($jalali ? 'saturday' : 'monday')->addDays($offset)),
            range(0, 6)
        );
    }

    /**
     * @return array{currentYear: int, currentMonth: int, years: array<int, string>, months: array<int, string>}
     */
    public function miniMonth(string $isoAnchor, bool $jalali): array
    {
        $anchor = CalendarRange::parseIso($isoAnchor);
        $current = $jalali ? Jalalian::fromCarbon($anchor) : null;
        $year = $jalali ? $current->getYear() : $anchor->year;
        $month = $jalali ? $current->getMonth() : $anchor->month;

        return [
            'currentYear' => $year,
            'currentMonth' => $month,
            'years' => $this->yearOptions($year),
            'months' => $this->monthOptions($year, $jalali),
        ];
    }

    private function withRules(Collection $hits, array $relations = ['rule']): Collection
    {
        return $hits instanceof EloquentCollection ? $hits->loadMissing($relations) : $hits;
    }

    private function monthCell(CalendarRange $range, Carbon $date, string $selectedIso, string $today, Collection $hits): array
    {
        $iso = $date->toDateString();

        return [
            'iso' => $iso,
            'day' => $range->jalali ? Jalalian::fromCarbon($date)->getDay() : $date->day,
            'isToday' => $iso === $today,
            'isPast' => $iso < $today,
            'isSelected' => $iso === $selectedIso,
            'count' => $hits->count(),
            'colors' => $hits->map(fn ($hit): ?string => $hit->rule?->color?->value)->filter()->unique()->take(3)->values()->all(),
            'hasOverdue' => $iso < $today && $hits->contains(fn ($hit): bool => $hit->rule?->type === RuleType::ACTION),
        ];
    }

    /**
     * @param  Collection<int, object{event_date: Carbon, rule_color: string, rule_type: string, hit_count: int}>  $rows
     * @return array{iso: string, day: int, isToday: bool, isPast: bool, isSelected: bool, count: int, colors: array<int, string>, hasOverdue: bool}
     */
    private function groupedCell(CalendarRange $range, Carbon $date, string $selectedIso, string $today, Collection $rows): array
    {
        $iso = $date->toDateString();

        return [
            'iso' => $iso,
            'day' => $range->jalali ? Jalalian::fromCarbon($date)->getDay() : $date->day,
            'isToday' => $iso === $today,
            'isPast' => $iso < $today,
            'isSelected' => $iso === $selectedIso,
            'count' => $rows->sum('hit_count'),
            'colors' => $rows->pluck('rule_color')->filter()->unique()->take(3)->values()->all(),
            'hasOverdue' => $iso < $today && $rows->contains(fn ($row): bool => $row->rule_type === RuleType::ACTION->value),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overdueFirst(Collection $hits, bool $isPast): array
    {
        [$overdue, $upcoming] = $hits
            ->sortBy([['label', 'asc']])
            ->partition(fn ($hit): bool => $isPast && $hit->rule?->type === RuleType::ACTION);

        return $overdue
            ->concat($upcoming)
            ->map(fn ($hit): array => [
                'id' => $hit->getKey(),
                'label' => $hit->label,
                'rule' => $hit->rule?->name,
                'color' => $hit->rule?->color?->value,
                'overdue' => $isPast && $hit->rule?->type === RuleType::ACTION,
                'url' => $hit->subject ? CalendarModules::url($hit->subject) : null,
            ])
            ->values()
            ->all();
    }

    private function dayLabel(CalendarRange $range, string $iso): string
    {
        $date = Carbon::parse($iso);

        return $range->jalali
            ? Jalalian::fromCarbon($date)->format('l، d F Y')
            : $date->locale(app()->getLocale())->translatedFormat('l, d F Y');
    }

    private function weekdayLabel(bool $jalali, Carbon $day): string
    {
        return $jalali
            ? Jalalian::fromCarbon($day)->format('l')
            : $day->locale(app()->getLocale())->translatedFormat('l');
    }

    /**
     * @return array<int, string>
     */
    private function yearOptions(int $year): array
    {
        return array_map(fn (int $option): string => (string) $option, range($year - 5, $year + 5));
    }

    /**
     * @return array<int, string>
     */
    private function monthOptions(int $year, bool $jalali): array
    {
        return array_map(fn (int $month): string => $this->monthLabel($month, $year, $jalali), range(1, 12));
    }

    private function monthLabel(int $month, int $year, bool $jalali): string
    {
        return $jalali
            ? (new Jalalian($year, $month, 1))->format('F')
            : Carbon::createFromDate($year, $month, 1)->locale(app()->getLocale())->translatedFormat('F');
    }
}
