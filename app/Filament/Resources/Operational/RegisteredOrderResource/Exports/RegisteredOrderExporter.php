<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Exports;

use App\Filament\Resources\Operational\RegisteredOrderResource\Imports\RegisteredOrderImporter;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class RegisteredOrderExporter
{
    /**
     * @return int the number of physical rows written (parents + items)
     */
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = RegisteredOrderImporter::columnLabels();

        $records = $query
            ->with(['sellerCompany', 'buyerCompany', 'status', 'currency', 'purchaseRequests', 'proformaInvoices', 'purchaseOrders', 'items.product'])
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
    protected static function parentRow(RegisteredOrder $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['ro_number'] = (string) $record->ro_number;
        $values['contract_no'] = static::escapeCsvFormula((string) ($record->contract_no ?? ''));
        $values['official_registration_no'] = static::escapeCsvFormula((string) ($record->official_registration_no ?? ''));
        $values['seller_id'] = $record->sellerCompany?->getLocalizedNameAttribute() ?? '';
        $values['buyer_id'] = $record->buyerCompany?->getLocalizedNameAttribute() ?? '';
        $values['status_id'] = $record->status?->getLocalizedNameAttribute() ?? '';
        $values['order_date'] = static::jalaliDate($record->order_date);
        $values['validity_date'] = static::jalaliDate($record->validity_date);
        $values['expected_delivery_date'] = static::jalaliDate($record->expected_delivery_date);
        $values['incoterms'] = (string) ($record->incoterms ?? '');
        $values['currency_id'] = $record->currency?->getLocalizedNameAttribute() ?? '';
        $values['currency_type'] = (string) ($record->currency_type ?? '');
        $values['insurance_number'] = static::escapeCsvFormula((string) ($record->insurance_number ?? ''));
        $values['insurance_provider'] = static::escapeCsvFormula((string) ($record->insurance_provider ?? ''));
        $values['insurance_date'] = static::jalaliDate($record->insurance_date);
        $values['notes'] = static::plainText($record->notes);
        $values['pr_numbers'] = $record->purchaseRequests->pluck('pr_number')->implode(',');
        $values['invoice_nos'] = $record->proformaInvoices->pluck('invoice_no')->implode(',');
        $values['po_numbers'] = $record->purchaseOrders->pluck('po_number')->implode(',');

        return array_values($values);
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function itemRow(RegisteredOrderItem $item, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['product_id'] = $item->product?->getLocalizedNameAttribute() ?? '';
        $values['quantity'] = static::numberValue($item->quantity);
        $values['unit'] = (string) ($item->unit ?? '');
        $values['unit_price'] = static::numberValue($item->unit_price);
        $values['net_weight'] = static::numberValue($item->net_weight);
        $values['gross_weight'] = static::numberValue($item->gross_weight);
        $values['entrance_fee'] = static::numberValue($item->entrance_fee);
        $values['shipping_cost'] = static::numberValue($item->shipping_cost);
        $values['extra_cost'] = static::numberValue($item->extra_cost);
        $values['packing_details'] = static::plainText($item->packing_details);
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
        return (new EscapeFormula)->escapeRecord([$value])[0];
    }

    protected static function jalaliDate(mixed $date): string
    {
        return $date ? jdate($date)->format('Y-m-d') : '';
    }
}
