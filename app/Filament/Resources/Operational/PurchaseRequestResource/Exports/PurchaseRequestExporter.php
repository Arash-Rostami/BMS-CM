<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Exports;

use App\Filament\Resources\Operational\PurchaseRequestResource\Imports\PurchaseRequestImporter;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\Writer;

class PurchaseRequestExporter
{
    /**
     * @return int the number of physical rows written (parents + items)
     */
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = PurchaseRequestImporter::columnLabels();

        $records = $query
            ->with(['requester', 'department', 'costCenter', 'status', 'items.product', 'items.status'])
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
    protected static function parentRow(PurchaseRequest $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['pr_number'] = (string) $record->pr_number;
        $values['requester_id'] = $record->requester?->email ?? '';
        $values['department_id'] = $record->department?->getLocalizedNameAttribute() ?? '';
        $values['cost_center_id'] = $record->costCenter?->getLocalizedNameAttribute() ?? '';
        $values['required_by_date'] = static::jalaliDate($record->required_by_date);
        $values['urgency_level'] = (string) ($record->urgency_level ?? '');
        $values['total_estimated_cost'] = static::numberValue($record->total_estimated_cost);
        $values['status_id'] = $record->status?->getLocalizedNameAttribute() ?? '';
        $values['notes'] = static::plainText($record->notes);

        return array_values($values);
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function itemRow(PurchaseRequestItem $item, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['product_id'] = $item->product?->getLocalizedNameAttribute() ?? '';
        $values['quantity'] = static::numberValue($item->quantity);
        $values['unit'] = (string) ($item->unit ?? '');
        $values['estimated_cost'] = static::numberValue($item->estimated_cost);
        $values['item_status_id'] = $item->status?->getLocalizedNameAttribute() ?? '';
        $values['item_notes'] = static::plainText($item->notes);

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
