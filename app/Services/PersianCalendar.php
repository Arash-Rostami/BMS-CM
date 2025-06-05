<?php

namespace App\Services;

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class PersianCalendar
{
    /**
     * Convert a Gregorian year to Jalali, or return the same year if not in 'fa' locale.
     */
    public function convertYear(int $gregorianYear): int
    {
        if (app()->isLocale('fa')  && ($gregorianYear > 2000)) {
            $carbon = Carbon::create($gregorianYear, 3, 21, 0, 0, 0, config('app.timezone'));
            return Jalalian::fromCarbon($carbon)->getYear();
        }

        return $gregorianYear;
    }

    /**
     * Generate an options array [gregorian => displayYear]
     * using Jalali when locale is 'fa', otherwise Gregorian.
     */
    public function yearOptions(int $past = 2, int $future = 5): array
    {
        $currentG = now()->year;
        $startG = $currentG - $past;
        $endG = $currentG + $future;
        $opts = [];

        for ($g = $startG; $g <= $endG; $g++) {
            $opts[$g] = (string)$this->convertYear($g);
        }

        return $opts;
    }

    /**
     * Convert a Jalali year (e.g. 1403) to Gregorian.
     *
     * @param int $jalaliYear
     * @return int
     */
    public function jalaliToGregorian(int $jalaliYear): int
    {
        $jalaliDate = "{$jalaliYear}-01-01";
        $carbon = Jalalian::fromFormat('Y-m-d', $jalaliDate)
            ->toCarbon();
        return $carbon->year;
    }
}
