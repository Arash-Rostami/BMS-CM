<?php

namespace App\Models\Traits\ProformaInvoice;

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

        $invoiceNo = $this->invoice_no ?? 'N/A';
        $status = '✅ ';
        $seller = $this->sellerCompany?->{$fa ? 'name' : 'english_name'} ?? $s('sellerCompany', 'فروشنده نامشخص', 'Unknown Seller');
        $buyer = $this->buyerCompany?->{$fa ? 'name' : 'english_name'} ?? $s('buyerCompany', 'گیرنده نامشخص', 'Unknown Buyer');

        if (!$withDates) {
            return "{$invoiceNo} ┆ {$seller} => {$buyer}";
        }

        $fmt = fn($d) => $d ? ($fa ? toPersianDate($d) : toGregorianDate($d)) : 'N/A';
        $invDate = $fmt($this->invoice_date);
        $valDate = $fmt($this->validity_date);
        $invLabel = $s('inv', 'تاریخ پیش فاکتور', 'Invoice Date');
        $valLabel = $s('val', 'اعتبار تا', 'Valid Until');

        return "{$status} {$invoiceNo} ┆ {$seller} ⪼ {$buyer} 🗓️ {$invLabel}: {$invDate} ┆ {$valLabel}: {$valDate}";
    }
}
