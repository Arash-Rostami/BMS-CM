<?php

namespace App\Services\Imports;

final class LocalizedMatcher
{
    public static function columnLabel(string $name, ?string $translationKey): string
    {
        $label = $translationKey ? __($translationKey) : \Illuminate\Support\Str::headline($name);

        return "{$label} ({$name})";
    }

    public static function localizedGuesses(string $translationKey): array
    {
        return collect(['en', 'fa', 'fr'])
            ->map(fn (string $locale) => trans($translationKey, [], $locale))
            ->filter(fn ($label) => is_string($label) && $label !== '' && $label !== $translationKey)
            ->unique()
            ->values()
            ->all();
    }

    public static function enumMap(string $translationKey): array
    {
        $map = [];

        foreach (['en', 'fa', 'fr'] as $locale) {
            $options = trans($translationKey, [], $locale);

            if (! is_array($options)) {
                continue;
            }

            foreach ($options as $key => $label) {
                $map[mb_strtolower((string) $key)] = $key;

                if (is_string($label)) {
                    $map[mb_strtolower($label)] = $key;
                }
            }
        }

        return $map;
    }

    public static function foldDigits(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $ascii = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $ascii, str_replace($persian, $ascii, $value));
    }
}
