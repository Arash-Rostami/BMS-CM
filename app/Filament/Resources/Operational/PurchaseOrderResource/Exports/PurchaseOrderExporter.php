<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Exports;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class PurchaseOrderExporter
{
    /**
     * @return int the number of physical rows written (parents + items)
     */
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['sellerCompany', 'buyerCompany', 'status', 'currency', 'items.product', 'creator', 'updater'])
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
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return [
            'id' => __('resources/purchaseOrder/strings.export.id'),
            'po_number' => __('resources/purchaseOrder/strings.export.po_number'),
            'seller' => __('resources/purchaseOrder/strings.export.seller'),
            'seller_english' => __('resources/purchaseOrder/strings.export.seller_english'),
            'buyer' => __('resources/purchaseOrder/strings.export.buyer'),
            'buyer_english' => __('resources/purchaseOrder/strings.export.buyer_english'),
            'status' => __('resources/purchaseOrder/strings.export.status'),
            'status_english' => __('resources/purchaseOrder/strings.export.status_english'),
            'order_date' => __('resources/purchaseOrder/strings.export.order_date'),
            'validity_date' => __('resources/purchaseOrder/strings.export.validity_date'),
            'expected_delivery_date' => __('resources/purchaseOrder/strings.export.expected_delivery_date'),
            'incoterms' => __('resources/purchaseOrder/strings.export.incoterms'),
            'shipping_address' => __('resources/purchaseOrder/strings.export.shipping_address'),
            'packing_details' => __('resources/purchaseOrder/strings.export.packing_details'),
            'currency' => __('resources/purchaseOrder/strings.export.currency'),
            'currency_english' => __('resources/purchaseOrder/strings.export.currency_english'),
            'notes' => __('resources/purchaseOrder/strings.export.notes'),
            'total_amount' => __('resources/purchaseOrder/strings.export.total_amount'),
            'total_quantity' => __('resources/purchaseOrder/strings.export.total_quantity'),
            'product' => __('resources/purchaseOrder/strings.export.product'),
            'quantity' => __('resources/purchaseOrder/strings.export.quantity'),
            'unit' => __('resources/purchaseOrder/strings.export.unit'),
            'unit_price' => __('resources/purchaseOrder/strings.export.unit_price'),
            'net_weight' => __('resources/purchaseOrder/strings.export.net_weight'),
            'gross_weight' => __('resources/purchaseOrder/strings.export.gross_weight'),
            'item_description' => __('resources/purchaseOrder/strings.export.item_description'),
            'creator' => __('resources/purchaseOrder/strings.export.creator'),
            'updater' => __('resources/purchaseOrder/strings.export.updater'),
            'created_at' => __('resources/purchaseOrder/strings.export.created_at'),
            'updated_at' => __('resources/purchaseOrder/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function parentRow(PurchaseOrder $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['po_number'] = (string) $record->po_number;
        $values['seller'] = $record->sellerCompany?->getLocalizedNameAttribute() ?? '';
        $values['seller_english'] = (string) ($record->sellerCompany?->english_name ?? '');
        $values['buyer'] = $record->buyerCompany?->getLocalizedNameAttribute() ?? '';
        $values['buyer_english'] = (string) ($record->buyerCompany?->english_name ?? '');
        $values['status'] = $record->status?->getLocalizedNameAttribute() ?? '';
        $values['status_english'] = (string) ($record->status?->english_name ?? '');
        $values['order_date'] = static::jalaliDate($record->order_date);
        $values['validity_date'] = static::jalaliDate($record->validity_date);
        $values['expected_delivery_date'] = static::jalaliDate($record->expected_delivery_date);
        $values['incoterms'] = (string) ($record->incoterms ?? '');
        $values['shipping_address'] = static::plainText($record->shipping_address);
        $values['packing_details'] = static::plainText($record->packing_details);
        $values['currency'] = $record->currency?->getLocalizedNameAttribute() ?? '';
        $values['currency_english'] = (string) ($record->currency?->english_name ?? '');
        $values['notes'] = static::plainText($record->notes);
        $values['total_amount'] = static::numberValue($record->items->sum(fn (PurchaseOrderItem $item) => $item->quantity * $item->unit_price));
        $values['total_quantity'] = static::numberValue($record->items->sum('quantity'));
        $values['creator'] = static::plainText($record->creator?->name ?? '');
        $values['updater'] = static::plainText($record->updater?->name ?? '');
        $values['created_at'] = static::jalaliDate($record->created_at);
        $values['updated_at'] = static::jalaliDate($record->updated_at);

        return array_values($values);
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function itemRow(PurchaseOrderItem $item, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['product'] = $item->product?->getLocalizedNameAttribute() ?? '';
        $values['quantity'] = static::numberValue($item->quantity);
        $values['unit'] = (string) ($item->unit ?? '');
        $values['unit_price'] = static::numberValue($item->unit_price);
        $values['net_weight'] = static::numberValue($item->net_weight);
        $values['gross_weight'] = static::numberValue($item->gross_weight);
        $values['item_description'] = static::plainText($item->description);

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
