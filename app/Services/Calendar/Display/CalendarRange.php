<?php

namespace App\Services\Calendar\Display;

use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

final readonly class CalendarRange
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
        public string $anchor,
        public bool $jalali,
    ) {}

    public static function forMonth(string $isoAnchor, bool $jalali): self
    {
        $anchor = self::parseIso($isoAnchor);
        $isoAnchor = $anchor->toDateString();

        if (! $jalali) {
            return new self($anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), $isoAnchor, false);
        }

        $month = Jalalian::fromCarbon($anchor);

        return new self(
            Carbon::instance((new Jalalian($month->getYear(), $month->getMonth(), 1))->toCarbon())->startOfDay(),
            Carbon::instance((new Jalalian($month->getYear(), $month->getMonth(), $month->getMonthDays()))->toCarbon())->endOfDay(),
            $isoAnchor,
            true,
        );
    }

    public static function parseIso(mixed $iso): Carbon
    {
        if (! is_string($iso)) {
            return today();
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $iso);

            return $date->toDateString() === $iso ? $date : today();
        } catch (\Throwable) {
            return today();
        }
    }

    /**
     * @return array<int, Carbon>
     */
    public function days(): array
    {
        $days = [];
        $cursor = $this->start->copy()->startOfDay();
        $end = $this->end->copy()->startOfDay();

        while ($cursor <= $end) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    public function label(): string
    {
        return $this->jalali
            ? Jalalian::fromCarbon($this->start)->format('F Y')
            : $this->start->copy()->locale(app()->getLocale())->translatedFormat('F Y');
    }

    public function weekdayOffset(): int
    {
        return $this->jalali
            ? Jalalian::fromCarbon($this->start)->getDayOfWeek()
            : ($this->start->dayOfWeek + 6) % 7;
    }

    public static function shiftMonths(string $iso, int $delta, bool $jalali): string
    {
        $date = self::parseIso($iso);

        if (! $jalali) {
            return $date->addMonthsNoOverflow($delta)->toDateString();
        }

        $current = Jalalian::fromCarbon($date);
        $index = $current->getYear() * 12 + $current->getMonth() - 1 + $delta;
        $year = (int) floor($index / 12);
        $month = $index - $year * 12 + 1;
        $day = min($current->getDay(), (new Jalalian($year, $month, 1))->getMonthDays());

        return (new Jalalian($year, $month, $day))->toCarbon()->toDateString();
    }
}
