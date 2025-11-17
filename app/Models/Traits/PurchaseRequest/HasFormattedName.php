<?php

namespace App\Models\Traits\PurchaseRequest;

trait HasFormattedName
{
    public function getFormattedNameAttribute(): string
    {
        return $this->buildFormattedName(true);
    }

    public function getFormattedNameWithoutDateAttribute(): string
    {
        return $this->buildFormattedName(false);
    }

    private function buildFormattedName(bool $withDates): string
    {
        $fa = app()->getLocale() === 'fa';
        $s = fn($key, $faValue, $enValue) => $fa ? $faValue : $enValue;

        $status = match ($this->status?->english_name) {
            'Authorized'  => '✅ ',
            'Conditional' => '☑️ ',
            default       => '',
        };

        $dept = $this->costCenter?->{$fa ? 'name' : 'english_name'}
            ?? $this->department?->{$fa ? 'name' : 'english_name'}
            ?? $s('dept', 'نامشخص', 'Unknown');

        $name = $this->requester?->name ?? 'N/A';
        $idValue = $this->pr_number ?? $this->id;

        if (! $withDates) {
            return $fa
                ? "{$status}{$name} ┆ {$idValue} ({$dept})"
                : "{$status}{$idValue} ┆ {$name} ({$dept})";
        }

        $fmt = fn($d) => $d ? ($fa ? toPersianDate($d) : toGregorianDate($d)) : 'N/A';
        $cDate = $fmt($this->created_at);

        [$dDate, $dLabel] = $this->required_by_date
            ? [$fmt($this->required_by_date), $s('label', 'نیاز تا', 'Required by')]
            : [$fmt($this->updated_at), $s('label', 'آخرین ویرایش', 'Last Edited')];

        $createdLabel = $s('created', 'ایجاد', 'Created');

        return $fa
            ? "{$status}{$name} ┆ {$idValue} ({$dept}) 🗓️ {$createdLabel}: {$cDate} ┆ {$dLabel}: {$dDate}"
            : "{$status}{$idValue} ┆ {$name} ({$dept}) 🗓️ {$createdLabel}: {$cDate} ┆ {$dLabel}: {$dDate}";
    }
}
