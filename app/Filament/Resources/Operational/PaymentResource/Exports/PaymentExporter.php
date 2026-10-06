<?php

namespace App\Filament\Resources\Operational\PaymentResource\Exports;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class PaymentExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with([
                'status', 'payor', 'payee', 'currency', 'bank', 'creator', 'updater',
                'targetable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    PurchaseOrder::class => ['creator', 'status'],
                    RegisteredOrder::class => ['sellerCompany', 'buyerCompany'],
                ]),
            ])
            ->orderBy('id')
            ->lazy();

        $stream = fopen($absolutePath, 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        $csv = Writer::from($stream);
        $csv->insertOne(array_values($labels));

        $rows = 0;

        foreach ($records as $record) {
            $csv->insertOne(static::parentRow($record, $labels));
            $rows++;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return [
            'id' => __('resources/payment/strings.export.id'),
            'payment_no' => __('resources/payment/strings.export.payment_no'),
            'payment_date' => __('resources/payment/strings.export.payment_date'),
            'payment_deadline' => __('resources/payment/strings.export.payment_deadline'),
            'status' => __('resources/payment/strings.export.status'),
            'status_english' => __('resources/payment/strings.export.status_english'),
            'targetable' => __('resources/payment/strings.export.targetable'),
            'payor' => __('resources/payment/strings.export.payor'),
            'payor_english' => __('resources/payment/strings.export.payor_english'),
            'payee' => __('resources/payment/strings.export.payee'),
            'payee_english' => __('resources/payment/strings.export.payee_english'),
            'bank' => __('resources/payment/strings.export.bank'),
            'bank_english' => __('resources/payment/strings.export.bank_english'),
            'beneficiary_name' => __('resources/payment/strings.export.beneficiary_name'),
            'beneficiary_address' => __('resources/payment/strings.export.beneficiary_address'),
            'bank_address' => __('resources/payment/strings.export.bank_address'),
            'account_no' => __('resources/payment/strings.export.account_no'),
            'swift' => __('resources/payment/strings.export.swift'),
            'iban' => __('resources/payment/strings.export.iban'),
            'currency' => __('resources/payment/strings.export.currency'),
            'currency_english' => __('resources/payment/strings.export.currency_english'),
            'payable_amount' => __('resources/payment/strings.export.payable_amount'),
            'bank_charges' => __('resources/payment/strings.export.bank_charges'),
            'total_amount' => __('resources/payment/strings.export.total_amount'),
            'exchange_rate' => __('resources/payment/strings.export.exchange_rate'),
            'calculated_total' => __('resources/payment/strings.export.calculated_total'),
            'total_ratio' => __('resources/payment/strings.export.total_ratio'),
            'notes' => __('resources/payment/strings.export.notes'),
            'creator' => __('resources/payment/strings.export.creator'),
            'updater' => __('resources/payment/strings.export.updater'),
            'created_at' => __('resources/payment/strings.export.created_at'),
            'updated_at' => __('resources/payment/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function parentRow(Payment $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['payment_no'] = (string) $record->payment_no;
        $values['payment_date'] = static::jalaliDate($record->payment_date);
        $values['payment_deadline'] = static::jalaliDate($record->payment_deadline);
        $values['status'] = $record->status?->getLocalizedNameAttribute() ?? '';
        $values['status_english'] = (string) ($record->status?->english_name ?? '');
        $values['targetable'] = $record->getTargetableDisplay();
        $values['payor'] = $record->payor?->getLocalizedNameAttribute() ?? '';
        $values['payor_english'] = (string) ($record->payor?->english_name ?? '');
        $values['payee'] = $record->payee?->getLocalizedNameAttribute() ?? '';
        $values['payee_english'] = (string) ($record->payee?->english_name ?? '');
        $values['bank'] = $record->bank?->getLocalizedNameAttribute() ?? '';
        $values['bank_english'] = (string) ($record->bank?->english_name ?? '');
        $values['beneficiary_name'] = static::plainText($record->beneficiary_name);
        $values['beneficiary_address'] = static::plainText($record->beneficiary_address);
        $values['bank_address'] = static::plainText($record->bank_address);
        $values['account_no'] = static::plainText($record->account_no);
        $values['swift'] = static::plainText($record->swift);
        $values['iban'] = static::plainText($record->iban);
        $values['currency'] = $record->currency?->getLocalizedNameAttribute() ?? '';
        $values['currency_english'] = (string) ($record->currency?->english_name ?? '');
        $values['payable_amount'] = static::numberValue($record->payable_amount);
        $values['bank_charges'] = static::numberValue($record->bank_charges);
        $values['total_amount'] = static::numberValue($record->total_amount);
        $values['exchange_rate'] = static::numberValue($record->exchange_rate);
        $values['calculated_total'] = static::numberValue($record->calculated_total);
        $values['total_ratio'] = static::numberValue($record->total_ratio);
        $values['notes'] = static::plainText($record->notes);
        $values['creator'] = static::plainText($record->creator?->name ?? '');
        $values['updater'] = static::plainText($record->updater?->name ?? '');
        $values['created_at'] = static::jalaliDate($record->created_at);
        $values['updated_at'] = static::jalaliDate($record->updated_at);

        return array_values($values);
    }

    protected static function numberValue(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    protected static function plainText(mixed $value): string
    {
        $text = trim(html_entity_decode(strip_tags((string) ($value ?? '')), ENT_QUOTES));

        return static::escapeCsvFormula($text);
    }

    protected static function escapeCsvFormula(string $value): string
    {
        return (new EscapeFormula)->escapeRecord([$value])[0];
    }

    protected static function jalaliDate(mixed $date): string
    {
        return $date ? jdate($date)->format('Y-m-d') : '';
    }
}
