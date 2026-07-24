<?php

namespace App\Models\Traits\Shipment;

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
        $s = fn ($key, $faValue, $enValue) => $fa ? $faValue : $enValue;

        $shipNo = $this->shipment_no ?? 'N/A';
        $carrier = $this->carrier?->{$fa ? 'name' : 'english_name'} ?? $s('carrier', 'حمل‌کننده نامشخص', 'Unknown Carrier');
        $status = '🚢 ';

        if (! $withDates) {
            return "{$status} {$shipNo} ┆ {$carrier}";
        }

        $fmt = fn ($d) => $d ? ($fa ? toPersianDate($d) : toGregorianDate($d)) : 'N/A';
        $eta = $fmt($this->eta);

        $etaLabel = $s('eta', 'تاریخ ورود', 'ETA');

        return "{$status} {$shipNo} ┆ {$carrier} 🗓️ {$etaLabel}: {$eta}";
    }
}
