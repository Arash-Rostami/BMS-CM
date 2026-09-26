<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Exports;

use App\Filament\Resources\Operational\ProformaInvoiceResource\Imports\ProformaInvoiceImporter;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceItem;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\Writer;

class ProformaInvoiceExporter
{
    /**
     * @return int the number of physical rows written (parents + items)
     */
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = ProformaInvoiceImporter::columnLabels();

        $records = $query
            ->with(['sellerCompany', 'buyerCompany', 'mainCurrency', 'secondaryCurrency', 'items.product'])
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

            foreach ($record->items as $item) {
                $csv->insertOne(static::itemRow($item, $labels));
                $rows++;
            }
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function parentRow(ProformaInvoice $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['invoice_no'] = (string) $record->invoice_no;
        $values['invoice_date'] = static::jalaliDate($record->invoice_date);
        $values['contract_no'] = static::escapeCsvFormula((string) ($record->contract_no ?? ''));
        $values['buyer_comm_card_num'] = static::escapeCsvFormula((string) ($record->buyer_comm_card_num ?? ''));
        $values['seller_id'] = $record->sellerCompany?->getLocalizedNameAttribute() ?? '';
        $values['buyer_id'] = $record->buyerCompany?->getLocalizedNameAttribute() ?? '';
        $values['validity_date'] = static::jalaliDate($record->validity_date);
        $values['beneficiary_country'] = (string) ($record->beneficiary_country ?? '');
        $values['origin_country'] = (string) ($record->origin_country ?? '');
        $values['destination_country'] = (string) ($record->destination_country ?? '');
        $values['transport_mode'] = (string) ($record->transport_mode ?? '');
        $values['port_of_discharge'] = static::escapeCsvFormula((string) ($record->port_of_discharge ?? ''));
        $values['port_of_loading'] = static::escapeCsvFormula((string) ($record->port_of_loading ?? ''));
        $values['delivery_terms'] = (string) ($record->delivery_terms ?? '');
        $values['main_currency_id'] = $record->mainCurrency?->getLocalizedNameAttribute() ?? '';
        $values['secondary_currency_id'] = $record->secondaryCurrency?->getLocalizedNameAttribute() ?? '';
        $values['discount'] = static::numberValue($record->discount);
        $values['freight_charges'] = static::numberValue($record->freight_charges);
        $values['other_charges'] = static::numberValue($record->other_charges);
        $values['total_amount'] = static::numberValue($record->total_amount);
        $values['notes'] = static::plainText($record->notes);

        return array_values($values);
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function itemRow(ProformaInvoiceItem $item, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['product_id'] = $item->product?->getLocalizedNameAttribute() ?? '';
        $values['origin'] = static::escapeCsvFormula((string) ($item->origin ?? ''));
        $values['hs_code'] = static::escapeCsvFormula((string) ($item->hs_code ?? ''));
        $values['unit'] = (string) ($item->unit ?? '');
        $values['quantity'] = static::numberValue($item->quantity);
        $values['unit_price'] = static::numberValue($item->unit_price);
        $values['net_weight'] = static::numberValue($item->net_weight);
        $values['gross_weight'] = static::numberValue($item->gross_weight);
        $values['item_freight_charges'] = static::numberValue($item->freight_charges);
        $values['item_total_amount'] = static::numberValue($item->total_amount);
        $values['description'] = static::plainText($item->description);

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
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    protected static function jalaliDate(mixed $date): string
    {
        return $date ? jdate($date)->format('Y-m-d') : '';
    }
}
