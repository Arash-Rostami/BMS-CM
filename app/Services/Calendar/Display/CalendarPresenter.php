<?php

namespace App\Services\Calendar\Display;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Models\CalendarHit;
use App\Services\Calendar\CalendarModules;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

class CalendarPresenter
{
    public const REASON_CONDITIONS = 2;

    /**
     * @var array<int, array{date: string, conditions: array<int, string>}>
     */
    private array $guides = [];

    /**
     * @return array{text: string, full: string}
     */
    public function reason(CalendarHit $hit): array
    {
        $rule = $hit->rule;

        if ($rule === null) {
            return ['text' => '', 'full' => ''];
        }

        $guide = $this->guides[$rule->id] ??= CalendarBoard::ruleReason($rule);
        $conditions = $guide['conditions'];
        $head = $guide['date'].': '.adaptiveDate($hit->event_date);

        return [
            'text' => $this->reasonLine($head, $this->because(array_slice($conditions, 0, self::REASON_CONDITIONS), max(0, count($conditions) - self::REASON_CONDITIONS), $rule->subject)),
            'full' => $this->reasonLine($head, $this->because($conditions, 0, $rule->subject)),
        ];
    }

    /**
     * @param  array<int, string>  $shown
     */
    private function because(array $shown, int $more, string $subject): string
    {
        if ($shown === []) {
            return __('resources/dashboard/strings.widgets.calendar.reason.all', ['module' => CalendarModules::label($subject)]);
        }

        return __('resources/dashboard/strings.widgets.calendar.reason.because', [
            'conditions' => implode(' · ', $shown).($more > 0 ? ' +'.$more : ''),
        ]);
    }

    private function reasonLine(string $head, string $because): string
    {
        return $head.' · '.$because;
    }

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
