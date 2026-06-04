<?php

namespace App\Services;

use App\Contracts\HasDocumentChecklist;
use Illuminate\Support\Facades\Lang;

/**
 * Auto-ticks document-checklist rows whose label matches an uploaded
 * attachment's original filename, across fa/fr/en and tolerant of misspelling.

 */
class DocChecklistMatcher
{
    private const LOCALES = ['fa', 'en', 'fr'];

    /** Levenshtein tolerance as a fraction of the candidate length. */
    private const FUZZY_RATIO = 0.25;

    public static function sync(HasDocumentChecklist $record): void
    {
        if (! $record->isDocumentTrackingEnabled()) {
            return; // Smart Tracer OFF => track nothing
        }

        $rows = $record->documentChecklist();
        if ($rows === []) {
            return;
        }

        $files = $record->attachments()->pluck('name')
            ->filter()
            ->map(fn (string $name): array => [
                'compact' => self::normalize($name),
                'tokens'  => self::tokenize($name),
            ])
            ->all();

        $options = self::optionLabels($record->documentChecklistOptions());
        $changed = false;

        foreach ($rows as $i => $row) {
            $present = self::rowMatches($row['name'] ?? '', $files, $options);

            if ((bool) ($row['received'] ?? false) !== $present) {
                $rows[$i]['received'] = $present;
                $changed = true;
            }
        }

        if ($changed) {
            $record->setDocumentChecklist($rows);
        }
    }

    private static function rowMatches(string $name, array $files, array $options): bool
    {
        if ($files === []) {
            return false;
        }

        $candidates = self::candidates($name, $options);

        foreach ($files as $file) {
            if (self::matches($file, $candidates)) {
                return true;
            }
        }

        return false;
    }


    /** @return array<string, array<string, string>> locale => [key => label] */
    private static function optionLabels(string $key): array
    {
        $options = [];

        foreach (self::LOCALES as $locale) {
            $set = Lang::get($key, [], $locale);
            if (is_array($set)) {
                $options[$locale] = $set;
            }
        }

        return $options;
    }

    /**
     * Build the normalised match candidates for one checklist row: the row's
     * own key/label, its fa/en/fr translations, plus any parenthetical
     * abbreviation (e.g. "Bill of Lading (BL)" -> "BL").
     *
     * @return array<int, array{compact: string, tokens: array<int, string>, short: bool}>
     */
    private static function candidates(string $name, array $options): array
    {
        $labels = [$name];

        foreach ($options as $set) {
            if (array_key_exists($name, $set)) {
                $labels[] = $set[$name];
            }
        }

        foreach ($labels as $label) {
            if (preg_match_all('/\(([^)]+)\)/u', (string) $label, $matches)) {
                array_push($labels, ...$matches[1]);
            }
        }

        $candidates = [];
        foreach (array_unique(array_filter($labels)) as $label) {
            $compact = self::normalize($label);
            if ($compact === '') {
                continue;
            }
            $candidates[] = [
                'compact' => $compact,
                'tokens' => array_filter(self::tokenize($label), fn ($t) => mb_strlen($t) >= 3),
                'short' => mb_strlen($compact) <= 3,
            ];
        }

        return $candidates;
    }

    private static function matches(array $file, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            ['compact' => $compact, 'tokens' => $tokens, 'short' => $short] = $candidate;

            // Short codes/abbreviations (bl, do, ci...) need an exact token, never a fuzzy one.
            if ($short) {
                if (in_array($compact, $file['tokens'], true)) {
                    return true;
                }
                continue;
            }

            // Direct substring catches "گزارش‌بازرسی" containing "بازرسی" or "bank-commitment".
            if ($file['compact'] !== '' && mb_strpos($file['compact'], $compact) !== false) {
                return true;
            }

            // Multi-word labels: every significant word must appear (fuzzily) in the filename.
            if (count($tokens) > 1) {
                if (self::everyTokenHit($tokens, $file['tokens'])) {
                    return true;
                }
                continue;
            }

            // Single-word label: fuzzy match against any filename token.
            if (self::tokenHit($compact, $file['tokens'])) {
                return true;
            }
        }

        return false;
    }

    private static function everyTokenHit(array $needles, array $haystack): bool
    {
        foreach ($needles as $needle) {
            if (! self::tokenHit($needle, $haystack)) {
                return false;
            }
        }

        return true;
    }

    private static function tokenHit(string $needle, array $tokens): bool
    {
        $len = mb_strlen($needle);
        $tolerance = max(1, (int) floor($len * self::FUZZY_RATIO));

        foreach ($tokens as $token) {
            if ($token === $needle) {
                return true;
            }
            if (abs(mb_strlen($token) - $len) > $tolerance) {
                continue;
            }
            if (self::levenshtein($token, $needle) <= $tolerance) {
                return true;
            }
        }

        return false;
    }

    /** Lowercase, fold Persian/Arabic variants, strip separators & punctuation. */
    private static function normalize(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', self::canonical($value));
    }

    /** @return array<int, string> normalised, separator-split tokens */
    private static function tokenize(string $value): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', self::canonical($value), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map(self::normalize(...), $parts)));
    }

    private static function canonical(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = strtr($value, [
            'ي' => 'ی', 'ك' => 'ک', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ی',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        // Strip Arabic diacritics + zero-width / direction marks.
        return preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{200c}-\x{200f}]/u', '', $value);
    }

    /** Multibyte-safe Levenshtein; PHP's built-in is byte-based and corrupts Persian. */
    private static function levenshtein(string $a, string $b): int
    {
        $a = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
        $la = count($a);
        $lb = count($b);

        if ($la === 0) {
            return $lb;
        }
        if ($lb === 0) {
            return $la;
        }

        $prev = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $cur;
        }

        return $prev[$lb];
    }
}
