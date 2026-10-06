<?php

namespace Tests\Feature\Helpers;

use App\Models\PurchaseRequest;
use App\Models\Status;
use DateTime;
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

        // Filament ≥4.13's TextEntry::formatState resolves isProse() → getRecord(),
        // which needs a bound record on a bare containerless entry.
        $entry = TextEntry::make('created_at')->model(new PurchaseRequest)->adaptiveDateTime();
        $this->assertSame(toGregorianDate('2026-09-17 14:30:00', true), $entry->formatState('2026-09-17 14:30:00'));

        $this->assertNull($column->formatState(null));
    }

    public function test_tab_badge_renders_the_label_and_the_count_span(): void
    {
        $this->assertSame('Documents', (string) tabBadge('Documents', null), 'A blank count renders the bare label without a badge span.');
        $this->assertSame('Documents', (string) tabBadge('Documents', ''));

        $this->assertSame(
            'Documents <span class="tb-badge tb-info">3</span>',
            (string) tabBadge('Documents', 3),
            'A non-blank count renders the label plus a tb-badge span carrying the count.'
        );
        $this->assertSame('Docs <span class="tb-badge tb-danger">2</span>', (string) tabBadge('Docs', 2, 'danger'));
        $this->assertSame('Docs <span class="tb-badge tb-warning">0</span>', (string) tabBadge('Docs', 0, 'warning'));
        $this->assertSame('Docs <span class="tb-badge tb-info">1</span>', (string) tabBadge('Docs', 1, 'purple'), 'An unmapped color falls back to tb-info.');
    }

    public function test_delimiter_formats_numbers_and_currency(): void
    {
        $this->assertSame('-', delimiter(null), 'A null or empty value renders as a dash.');
        $this->assertSame('-', delimiter(''));

        $this->assertSame('1,234.50', delimiter(1234.5));
        $this->assertSame('1,234.57', delimiter(1234.5678));
        $this->assertSame('1,234.568', delimiter(1234.5678, null, 3));
        $this->assertSame('1,235', delimiter(1234.5, null, 0));

        $this->assertSame('1,234.50 USD', delimiter(1234.5, 'usd'), 'A 1-4 letter currency code is appended upper-cased after the amount.');
        $this->assertSame('€ 1,234.50', delimiter(1234.5, '€'), 'A non-letter currency is prefixed before the amount.');
        $this->assertSame('ریال 1,234.50', delimiter(1234.5, 'ریال'));
    }

    public function test_get_localized_name_resolves_the_related_name_by_locale(): void
    {
        $record = (new PurchaseRequest)->setRelation('status', new Status(['name' => 'تایید شده', 'english_name' => 'Authorized']));

        app()->setLocale('fa');
        $this->assertSame('تایید شده', getLocalizedName($record, 'status'), 'Under the fa locale the localized name is the relation name attribute.');

        app()->setLocale('en');
        $this->assertSame('Authorized', getLocalizedName($record, 'status'), 'Under non-fa locales the localized name is the relation english_name attribute.');

        app()->setLocale('fr');
        $this->assertSame('Authorized', getLocalizedName($record, 'status'));

        $this->assertNull(getLocalizedName((object) ['status' => null], 'status'), 'A missing relation resolves null without error.');
    }

    public function test_to_gregorian_date_formats_known_dates(): void
    {
        $this->assertSame('-', toGregorianDate(null), 'A null date renders as a dash.');
        $this->assertSame('-', toGregorianDate(''));

        $this->assertSame('2026 September 17', toGregorianDate('2026-09-17'));
        $this->assertSame('2026 September 17 - 14:30:05', toGregorianDate('2026-09-17 14:30:05', true));
        $this->assertSame('2026 September 17', toGregorianDate(new DateTime('2026-09-17')));
    }

    public function test_to_persian_date_renders_the_jalali_calendar_form(): void
    {
        $this->assertSame('-', toPersianDate(null), 'A null date renders as a dash.');

        $persian = toPersianDate('2026-09-17');
        $this->assertNotSame(toGregorianDate('2026-09-17'), $persian, 'The Persian rendering must not echo the Gregorian one.');
        $this->assertMatchesRegularExpression(
            '/^\d{2} [\x{0600}-\x{06FF}]+ \d{4}$/u',
            $persian,
            'The Jalali date renders a two-digit day, a Persian month name, and a four-digit year.'
        );

        $withTime = toPersianDate('2026-09-17 14:30:05', true);
        $this->assertMatchesRegularExpression(
            '/^\d{2} [\x{0600}-\x{06FF}]+ \d{4} - \d{2}:\d{2}:\d{2}$/u',
            $withTime,
            'The with-time form appends a - H:i:s time part after the date.'
        );
    }

    public function test_to_ymd_date_formats_dates_and_falls_back_to_created_at(): void
    {
        $this->assertSame('2026-09-17', toYmdDate(new PurchaseRequest, '2026-09-17 14:30:00'));
        $this->assertSame('2026-09-17', toYmdDate(new PurchaseRequest, new DateTime('2026-09-17 14:30:00')));

        $this->assertSame('—', toYmdDate(new PurchaseRequest), 'A record with no created_at renders the em dash fallback.');

        $record = (new PurchaseRequest)->forceFill(['created_at' => '2026-01-15 10:00:00']);
        $this->assertSame('2026-01-15', toYmdDate($record), 'Without an explicit date the record created_at is rendered as Y-m-d.');
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
