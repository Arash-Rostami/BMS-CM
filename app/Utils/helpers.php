<?php

if (!function_exists('toPersianDate')) {
    function toPersianDate(\DateTime|string|null $date): string
    {
        if (!$date) {
            return '-';
        }

        return jdate($date)->format('d F Y');
    }
}

if (!function_exists('toGregorianDate')) {
    function toGregorianDate(\DateTime|string|null $date): string
    {
        if (!$date) {
            return '-';
        }

        $date = is_string($date) ? new \DateTime($date) : $date;

        return $date->format('Y F d');
    }
}
