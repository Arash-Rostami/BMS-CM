<?php

namespace App\Models\Traits\PurchaseOrder;

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


        $name = $this->creator?->name ?? 'N/A';

        $status = match ($this->status?->english_name) {
            'Approved'  => '✅ ',
            'Submitted' => '☑️ ',
            default       => '',
        };

        $id   = $status . ($this->po_number ?? $this->id);

        if (!$withDates) {
            return "{$id} ┆ {$name}";
        }

        $fmt = fn($d) => $d ? ($fa ? toPersianDate($d) : toGregorianDate($d)) : 'N/A';
        $cDate = $fmt($this->created_at);

        [$dDate, $dLabel] =
            $this->validity_date
                ? [$fmt($this->validity_date), $s('label', 'معتبر تا', 'Valid by')]
                : [$fmt($this->updated_at), $s('label', 'آخرین ویرایش', 'Last Edited')];

        $createdLabel = $s('created', 'ایجاد', 'Created');
        return "{$id} ┆ {$name}  🗓️ {$createdLabel}: {$cDate} ┆ {$dLabel}: {$dDate}";
    }
}
