<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Exports;

use App\Models\BankProfile;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class BankProfileExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['registeredOrder', 'company', 'bank', 'status', 'requestedCurrency', 'purchasedCurrency', 'targetable', 'creator', 'updater'])
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
            'id' => __('resources/bankProfile/strings.export.id'),
            'bp_number' => __('resources/bankProfile/strings.export.bp_number'),
            'registered_order' => __('resources/bankProfile/strings.export.registered_order'),
            'order_number' => __('resources/bankProfile/strings.export.order_number'),
            'status' => __('resources/bankProfile/strings.export.status'),
            'status_english' => __('resources/bankProfile/strings.export.status_english'),
            'company' => __('resources/bankProfile/strings.export.company'),
            'company_english' => __('resources/bankProfile/strings.export.company_english'),
            'bank' => __('resources/bankProfile/strings.export.bank'),
            'bank_english' => __('resources/bankProfile/strings.export.bank_english'),
            'targetable' => __('resources/bankProfile/strings.export.targetable'),
            'supply_source' => __('resources/bankProfile/strings.export.supply_source'),
            'requested_currency' => __('resources/bankProfile/strings.export.requested_currency'),
            'requested_currency_english' => __('resources/bankProfile/strings.export.requested_currency_english'),
            'requested_amount' => __('resources/bankProfile/strings.export.requested_amount'),
            'purchased_currency' => __('resources/bankProfile/strings.export.purchased_currency'),
            'purchased_currency_english' => __('resources/bankProfile/strings.export.purchased_currency_english'),
            'purchased_equivalent' => __('resources/bankProfile/strings.export.purchased_equivalent'),
            'documents_amount' => __('resources/bankProfile/strings.export.documents_amount'),
            'commission_rate' => __('resources/bankProfile/strings.export.commission_rate'),
            'exchange_rate' => __('resources/bankProfile/strings.export.exchange_rate'),
            'final_rate' => __('resources/bankProfile/strings.export.final_rate'),
            'conversion_rate' => __('resources/bankProfile/strings.export.conversion_rate'),
            'creation_date' => __('resources/bankProfile/strings.export.creation_date'),
            'allocation_date' => __('resources/bankProfile/strings.export.allocation_date'),
            'purchase_date' => __('resources/bankProfile/strings.export.purchase_date'),
            'delivery_date' => __('resources/bankProfile/strings.export.delivery_date'),
            'payment_due_date' => __('resources/bankProfile/strings.export.payment_due_date'),
            'commitment_payment_date' => __('resources/bankProfile/strings.export.commitment_payment_date'),
            'notes' => __('resources/bankProfile/strings.export.notes'),
            'commission_amount' => __('resources/bankProfile/strings.export.commission_amount'),
            'commission_equivalent' => __('resources/bankProfile/strings.export.commission_equivalent'),
            'final_equivalent' => __('resources/bankProfile/strings.export.final_equivalent'),
            'remaining_commitment' => __('resources/bankProfile/strings.export.remaining_commitment'),
            'total_rial' => __('resources/bankProfile/strings.export.total_rial'),
            'total_purchased_remittance' => __('resources/bankProfile/strings.export.total_purchased_remittance'),
            'total_requested_remittance' => __('resources/bankProfile/strings.export.total_requested_remittance'),
            'creator' => __('resources/bankProfile/strings.export.creator'),
            'updater' => __('resources/bankProfile/strings.export.updater'),
            'created_at' => __('resources/bankProfile/strings.export.created_at'),
            'updated_at' => __('resources/bankProfile/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function parentRow(BankProfile $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['bp_number'] = (string) $record->bp_number;
        $values['registered_order'] = (string) ($record->registeredOrder?->ro_number ?? '');
        $values['order_number'] = static::plainText($record->order_number);
        $values['status'] = $record->status?->getLocalizedNameAttribute() ?? '';
        $values['status_english'] = (string) ($record->status?->english_name ?? '');
        $values['company'] = $record->company?->getLocalizedNameAttribute() ?? '';
        $values['company_english'] = (string) ($record->company?->english_name ?? '');
        $values['bank'] = $record->bank?->getLocalizedNameAttribute() ?? '';
        $values['bank_english'] = (string) ($record->bank?->english_name ?? '');
        $values['targetable'] = $record->getTargetableFormatted('export');
        $values['supply_source'] = $record->supply_source ? __('resources/bankProfile/strings.general.supply_sources.'.$record->supply_source) : '';
        $values['requested_currency'] = $record->requestedCurrency?->getLocalizedNameAttribute() ?? '';
        $values['requested_currency_english'] = (string) ($record->requestedCurrency?->english_name ?? '');
        $values['requested_amount'] = static::numberValue($record->requested_amount);
        $values['purchased_currency'] = $record->purchasedCurrency?->getLocalizedNameAttribute() ?? '';
        $values['purchased_currency_english'] = (string) ($record->purchasedCurrency?->english_name ?? '');
        $values['purchased_equivalent'] = static::numberValue($record->purchased_equivalent);
        $values['documents_amount'] = static::numberValue($record->documents_amount);
        $values['commission_rate'] = static::numberValue($record->commission_rate);
        $values['exchange_rate'] = static::numberValue($record->exchange_rate);
        $values['final_rate'] = static::numberValue($record->final_rate);
        $values['conversion_rate'] = static::numberValue($record->conversion_rate);
        $values['creation_date'] = static::jalaliDate($record->creation_date);
        $values['allocation_date'] = static::jalaliDate($record->allocation_date);
        $values['purchase_date'] = static::jalaliDate($record->purchase_date);
        $values['delivery_date'] = static::jalaliDate($record->delivery_date);
        $values['payment_due_date'] = static::jalaliDate($record->payment_due_date);
        $values['commitment_payment_date'] = static::jalaliDate($record->commitment_payment_date);
        $values['notes'] = static::plainText($record->notes);
        $values['commission_amount'] = static::numberValue($record->commission_amount_purchased);
        $values['commission_equivalent'] = static::numberValue($record->commission_equivalent);
        $values['final_equivalent'] = static::numberValue($record->final_equivalent);
        $values['remaining_commitment'] = static::numberValue($record->remaining_commitment);
        $values['total_rial'] = static::numberValue($record->total_rial_remittance);
        $values['total_purchased_remittance'] = static::numberValue($record->total_purchased_remittance);
        $values['total_requested_remittance'] = static::numberValue($record->total_requested_remittance);
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
