<?php

namespace App\Models\Traits\Custom;

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

        $declNo = $this->declaration_no ?? 'N/A';
        $shipment = $this->shipment?->shipment_no ?? '—';
        $status = '🛃 ';

        if (!$withDates) {
            return "{$status} {$declNo} ┆ {$shipment}";
        }

        $fmt = fn($d) => $d ? ($fa ? toPersianDate($d) : toGregorianDate($d)) : 'N/A';
        $clearanceDate = $fmt($this->clearance_date);

        $dateLabel = $s('date', 'تاریخ ترخیص', 'Clearance Date');

        return "{$status} {$declNo} ┆ {$shipment} 🗓️ {$dateLabel}: {$clearanceDate}";
    }
}
