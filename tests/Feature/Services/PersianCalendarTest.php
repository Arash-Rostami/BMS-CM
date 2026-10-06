<?php

namespace Tests\Feature\Services;

use App\Services\PersianCalendar;
use Tests\TestCase;

class PersianCalendarTest extends TestCase
{
    private string $locale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = app()->getLocale();
    }

    protected function tearDown(): void
    {
        app()->setLocale($this->locale);
        parent::tearDown();
    }

    public function test_convert_year_passes_through_outside_the_fa_locale(): void
    {
        app()->setLocale('en');

        $this->assertSame(2026, app(PersianCalendar::class)->convertYear(2026));
    }

    public function test_convert_year_passes_through_for_sentinel_years_in_the_fa_locale(): void
    {
        app()->setLocale('fa');

        $this->assertSame(2000, app(PersianCalendar::class)->convertYear(2000));
        $this->assertSame(1999, app(PersianCalendar::class)->convertYear(1999));
    }

    public function test_convert_year_anchors_at_nowruz_in_the_fa_locale(): void
    {
        app()->setLocale('fa');

        $this->assertSame(1405, app(PersianCalendar::class)->convertYear(2026));
    }

    public function test_jalali_to_gregorian_round_trips_a_converted_year(): void
    {
        app()->setLocale('fa');
        $service = app(PersianCalendar::class);

        $this->assertSame(2026, $service->jalaliToGregorian(1405));
        $this->assertSame(1405, $service->convertYear($service->jalaliToGregorian(1405)));
    }

    public function test_year_options_maps_gregorian_keys_to_the_display_years(): void
    {
        app()->setLocale('en');
        $year = now()->year;

        $options = app(PersianCalendar::class)->yearOptions(1, 2);

        $this->assertSame([$year - 1, $year, $year + 1, $year + 2], array_keys($options));
        $this->assertSame((string) $year, $options[$year]);
    }

    public function test_year_options_shows_jalali_years_in_the_fa_locale(): void
    {
        app()->setLocale('fa');
        $year = now()->year;

        $options = app(PersianCalendar::class)->yearOptions(0, 0);

        $this->assertNotSame((string) $year, $options[$year]);
    }
}