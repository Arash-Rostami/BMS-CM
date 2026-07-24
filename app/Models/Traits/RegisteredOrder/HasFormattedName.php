<?php

namespace App\Models\Traits\RegisteredOrder;

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

        $roNumber = $this->ro_number ?? 'N/A';
        $status = '✅ ';
        $seller = $this->sellerCompany?->{$fa ? 'name' : 'english_name'} ?? $s('sellerCompany', 'فروشنده نامشخص', 'Unknown Seller');
        $buyer = $this->buyerCompany?->{$fa ? 'name' : 'english_name'} ?? $s('buyerCompany', 'گیرنده نامشخص', 'Unknown Buyer');

        if (! $withDates) {
            return "{$roNumber} ┆ {$seller} => {$buyer}";
        }

        $fmt = fn ($d) => $d ? ($fa ? toPersianDate($d) : toGregorianDate($d)) : 'N/A';
        $ordDate = $fmt($this->order_date);
        $valDate = $fmt($this->validity_date);

        $ordLabel = $s('ord', 'تاریخ سفارش', 'Order Date');
        $valLabel = $s('val', 'اعتبار تا', 'Valid Until');

        return "{$status} {$roNumber} ┆ {$seller} ⪼ {$buyer} 🗓️ {$ordLabel}: {$ordDate} ┆ {$valLabel}: {$valDate}";
    }
}
