<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Exports;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class ShipmentExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['registeredOrder', 'carrier', 'status', 'containerStatus', 'operationStatus', 'trackingStatus', 'docStatus', 'creator', 'updater'])
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
            'id' => __('resources/shipment/strings.export.id'),
            'shipment_no' => __('resources/shipment/strings.export.shipment_no'),
            'registered_order' => __('resources/shipment/strings.export.registered_order'),
            'contract_no' => __('resources/shipment/strings.export.contract_no'),
            'part' => __('resources/shipment/strings.export.part'),
            'carrier' => __('resources/shipment/strings.export.carrier'),
            'carrier_english' => __('resources/shipment/strings.export.carrier_english'),
            'warehouse_date' => __('resources/shipment/strings.export.warehouse_date'),
            'exit_date' => __('resources/shipment/strings.export.exit_date'),
            'eta' => __('resources/shipment/strings.export.eta'),
            'etd' => __('resources/shipment/strings.export.etd'),
            'bl_number' => __('resources/shipment/strings.export.bl_number'),
            'booking_no' => __('resources/shipment/strings.export.booking_no'),
            'container_no' => __('resources/shipment/strings.export.container_no'),
            'container_type' => __('resources/shipment/strings.export.container_type'),
            'remittance_amount' => __('resources/shipment/strings.export.remittance_amount'),
            'customs_quantity' => __('resources/shipment/strings.export.customs_quantity'),
            'shipped_quantity' => __('resources/shipment/strings.export.shipped_quantity'),
            'status' => __('resources/shipment/strings.export.status'),
            'status_english' => __('resources/shipment/strings.export.status_english'),
            'shipment_status' => __('resources/shipment/strings.export.shipment_status'),
            'shipment_status_english' => __('resources/shipment/strings.export.shipment_status_english'),
            'operation_status' => __('resources/shipment/strings.export.operation_status'),
            'operation_status_english' => __('resources/shipment/strings.export.operation_status_english'),
            'container_status' => __('resources/shipment/strings.export.container_status'),
            'container_status_english' => __('resources/shipment/strings.export.container_status_english'),
            'doc_status' => __('resources/shipment/strings.export.doc_status'),
            'doc_status_english' => __('resources/shipment/strings.export.doc_status_english'),
            'documents' => __('resources/shipment/strings.export.documents'),
            'notes' => __('resources/shipment/strings.export.notes'),
            'creator' => __('resources/shipment/strings.export.creator'),
            'updater' => __('resources/shipment/strings.export.updater'),
            'created_at' => __('resources/shipment/strings.export.created_at'),
            'updated_at' => __('resources/shipment/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function parentRow(Shipment $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['shipment_no'] = (string) $record->shipment_no;
        $values['registered_order'] = (string) ($record->registeredOrder?->ro_number ?? '');
        $values['contract_no'] = static::plainText($record->contract_no);
        $values['part'] = static::plainText($record->part);
        $values['carrier'] = $record->carrier?->getLocalizedNameAttribute() ?? '';
        $values['carrier_english'] = (string) ($record->carrier?->english_name ?? '');
        $values['warehouse_date'] = static::jalaliDate($record->warehouse_date);
        $values['exit_date'] = static::jalaliDate($record->exit_date);
        $values['eta'] = static::jalaliDate($record->eta);
        $values['etd'] = static::jalaliDate($record->etd);
        $values['bl_number'] = static::plainText($record->bl_number);
        $values['booking_no'] = static::plainText($record->booking_no);
        $values['container_no'] = static::plainText($record->container_no);
        $values['container_type'] = static::plainText($record->container_type);
        $values['remittance_amount'] = static::numberValue($record->remittance_amount);
        $values['customs_quantity'] = static::numberValue($record->customs_quantity);
        $values['shipped_quantity'] = static::numberValue($record->shipped_quantity);
        $values['status'] = $record->status?->getLocalizedNameAttribute() ?? '';
        $values['status_english'] = (string) ($record->status?->english_name ?? '');
        $values['shipment_status'] = $record->trackingStatus?->getLocalizedNameAttribute() ?? '';
        $values['shipment_status_english'] = (string) ($record->trackingStatus?->english_name ?? '');
        $values['operation_status'] = $record->operationStatus?->getLocalizedNameAttribute() ?? '';
        $values['operation_status_english'] = (string) ($record->operationStatus?->english_name ?? '');
        $values['container_status'] = $record->containerStatus?->getLocalizedNameAttribute() ?? '';
        $values['container_status_english'] = (string) ($record->containerStatus?->english_name ?? '');
        $values['doc_status'] = $record->docStatus?->getLocalizedNameAttribute() ?? '';
        $values['doc_status_english'] = (string) ($record->docStatus?->english_name ?? '');
        $values['documents'] = static::documentsSummary($record);
        $values['notes'] = static::plainText($record->notes);
        $values['creator'] = static::plainText($record->creator?->name ?? '');
        $values['updater'] = static::plainText($record->updater?->name ?? '');
        $values['created_at'] = static::jalaliDate($record->created_at);
        $values['updated_at'] = static::jalaliDate($record->updated_at);

        return array_values($values);
    }

    protected static function documentsSummary(Shipment $record): string
    {
        $docs = $record->docs['items'] ?? [];

        if (! is_array($docs) || $docs === []) {
            return '';
        }

        return collect($docs)->map(function ($item) {
            $name = $item['name'] ?? '';
            $received = ($item['received'] ?? false) ? '✅' : '❌';

            return "{$name}: {$received}";
        })->implode(' | ');
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
