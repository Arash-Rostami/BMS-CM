<?php

if (!function_exists('toPersianDate')) {
    function toPersianDate(DateTime|string|null $date): string
    {
        if (!$date) {
            return '-';
        }

        return jdate($date)->format('d F Y');
    }
}

if (!function_exists('toGregorianDate')) {
    function toGregorianDate(DateTime|string|null $date): string
    {
        if (!$date) {
            return '-';
        }

        $date = is_string($date) ? new DateTime($date) : $date;

        return $date->format('Y F d');
    }
}


if (!function_exists('getLocalizedName')) {
    function getLocalizedName(object $record, string $relationship): ?string
    {
        return app()->getLocale() === 'fa'
            ? $record->{$relationship}?->name
            : $record->{$relationship}?->english_name;
    }
}

if (!function_exists('toYmdDate')) {
    function toYmdDate($record, DateTime|string|null $date = null): string
    {
        if (!$date) {
            return $record->created_at ? $record->created_at->format('Y-m-d') : '—';
        }

        $date = is_string($date) ? new DateTime($date) : $date;

        return $date->format('Y-m-d');
    }
}
if (!function_exists('delimiter')) {

    function delimiter($value, ?string $currency = null, int $decimals = 2): string
    {
        if ($value === null || $value === '') return '-';
        $formatted = number_format((float)$value, $decimals, '.', ',');

        if (!$currency) return $formatted;
        $currency = (string)$currency;

        if (preg_match('/^[A-Za-z]{1,4}$/', $currency)) return $formatted . ' ' . strtoupper($currency);
        return $currency .' ' .$formatted;
    }
}
