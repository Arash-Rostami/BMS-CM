<?php

namespace Tests\Unit;

use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdaptiveDateTest extends TestCase
{
    public function test_adaptive_date_follows_the_calendar_session_not_the_locale(): void
    {
        session(['calendar_type' => 'jalali']);
        app()->setLocale('en');

        $this->assertSame(toPersianDate('2026-09-17'), adaptiveDate('2026-09-17'));
        $this->assertSame(toPersianDate('2026-09-17 14:30:00', true), adaptiveDate('2026-09-17 14:30:00', true));

        session(['calendar_type' => 'gregorian']);

        $this->assertSame(toGregorianDate('2026-09-17'), adaptiveDate('2026-09-17'));
    }

    public function test_is_jalali_calendar_defaults_from_locale_when_session_unset(): void
    {
        session()->forget('calendar_type');

        app()->setLocale('fa');
        $this->assertTrue(isJalaliCalendar());

        app()->setLocale('en');
        $this->assertFalse(isJalaliCalendar());
    }

    public function test_adaptive_date_macros_format_columns_by_calendar_session(): void
    {
        session(['calendar_type' => 'jalali']);
        app()->setLocale('en');

        $column = TextColumn::make('order_date')->adaptiveDate();
        $this->assertSame(toPersianDate('2026-09-17'), $column->formatState('2026-09-17'));

        session(['calendar_type' => 'gregorian']);

        $column = TextColumn::make('order_date')->adaptiveDate();
        $this->assertSame(toGregorianDate('2026-09-17'), $column->formatState('2026-09-17'));

        $entry = TextEntry::make('created_at')->adaptiveDateTime();
        $this->assertSame(toGregorianDate('2026-09-17 14:30:00', true), $entry->formatState('2026-09-17 14:30:00'));

        $this->assertNull($column->formatState(null));
    }

    public function test_no_locale_locked_date_branches_or_raw_date_tokens_remain(): void
    {
        $files = File::allFiles(app_path('Filament/Resources'));

        foreach ($files as $file) {
            $contents = $file->getContents();
            $path = str_replace(base_path().'/', '', $file->getPathname());

            if (str_contains($contents, "app()->getLocale() === 'fa' ? toPersianDate")) {
                $this->fail("Locale-locked date branch found in {$path} — use adaptiveDate()/->adaptiveDate() instead.");
            }

            if (str_contains($contents, '->jalali(app()')) {
                $this->fail("Locale-locked jalali picker found in {$path} — use ->adaptive() instead.");
            }

            if (str_ends_with($file->getFilename(), 'Table.php') || str_ends_with($file->getFilename(), 'Infolist.php')) {
                if (preg_match('/->(date|dateTime)\(/', $contents)) {
                    $this->fail("Raw ->date()/->dateTime() found in {$path} — use ->adaptiveDate()/->adaptiveDateTime() so the calendar toggle is respected.");
                }
            }
        }

        $this->assertTrue(true);
    }
}
