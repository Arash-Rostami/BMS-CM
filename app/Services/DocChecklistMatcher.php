<?php

namespace App\Services;

use App\Contracts\HasDocumentChecklist;
use Illuminate\Support\Facades\Lang;

/**
 * Auto-ticks checklist rows whose fa/en/fr label (the *value*, not the key)
 * appears in an uploaded attachment's filename. The reserved 'track' row is
 * the Smart Tracer switch and is never matched.
 */
class DocChecklistMatcher
{
    private const LOCALES = ['en', 'fa', 'fr'];

    public static function sync(HasDocumentChecklist $record): void
    {
        if (! $record->isDocumentTrackingEnabled()) {
            return;
        }

        $rows = $record->documentChecklist();
        if ($rows === []) {
            return;
        }

        $files = $record->attachments()->pluck('name')->filter()
            ->map(fn (string $n): array => ['compact' => self::normalize($n), 'tokens' => self::tokens($n)])
            ->all();

        $labels = self::labelsByKey($record->documentChecklistOptions());
        $changed = false;

        foreach ($rows as $i => $row) {
            $name = $row['name'] ?? '';
            if (($row['name'] ?? '') === 'track') {
                continue;
            }

            $present = self::present($labels[$name] ?? [$name], $files);

            if ((bool) ($row['received'] ?? false) !== $present) {
                $rows[$i]['received'] = $present;
                $changed = true;
            }
        }

        if ($changed) {
            $record->setDocumentChecklist($rows);
        }
    }

    /** A row is present when any of its labels (or its (CODE) abbreviation) is found in a filename. */
    private static function present(array $rowLabels, array $files): bool
    {
        $needles = [];
        foreach ($rowLabels as $label) {
            $needles[] = self::normalize($label);
            if (preg_match('/\(([^)]+)\)/u', (string) $label, $m)) {
                $needles[] = self::normalize($m[1]); // "Commercial Invoice (CI)" -> "ci"
            }
        }
        $needles = array_filter(array_unique($needles));

        foreach ($files as $file) {
            foreach ($needles as $needle) {
                $hit = mb_strlen($needle) <= 3
                    ? in_array($needle, $file['tokens'], true)   // short codes must be a whole token (avoids "do" in "document")
                    : str_contains($file['compact'], $needle);
                if ($hit) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<string, string[]> option key => its label in every locale */
    private static function labelsByKey(string $key): array
    {
        $byKey = [];
        foreach (self::LOCALES as $locale) {
            foreach ((array) Lang::get($key, [], $locale) as $k => $label) {
                $byKey[$k][] = $label;
            }
        }

        return $byKey;
    }

    private static function tokens(string $value): array
    {
        return array_values(array_filter(
            array_map(self::normalize(...), preg_split('/[^\p{L}\p{N}]+/u', self::canonical($value), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        ));
    }

    private static function normalize(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', self::canonical($value));
    }

    /** Lowercase + fold Persian/Arabic variants + strip diacritics/zero-width marks. */
    private static function canonical(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'ي' => 'ی', 'ك' => 'ک', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ی',
        ]);

        return preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{200c}-\x{200f}]/u', '', $value);
    }
}
