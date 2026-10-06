<?php

namespace App\Filament\Resources\Operational\CustomResource\Exports;

use App\Models\Custom;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class CustomExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['shipment', 'registeredOrder', 'clearanceStatus', 'bankGuaranteeStatus', 'commitmentStatus', 'creator', 'updater'])
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
            'id' => __('resources/custom/strings.export.id'),
            'custom_no' => __('resources/custom/strings.export.custom_no'),
            'declaration_no' => __('resources/custom/strings.export.declaration_no'),
            'contract_no' => __('resources/custom/strings.export.contract_no'),
            'shipment_no' => __('resources/custom/strings.export.shipment_no'),
            'registered_order' => __('resources/custom/strings.export.registered_order'),
            'clearance_type' => __('resources/custom/strings.export.clearance_type'),
            'commitment_balance' => __('resources/custom/strings.export.commitment_balance'),
            'clearance_date' => __('resources/custom/strings.export.clearance_date'),
            'doc_submission_date' => __('resources/custom/strings.export.doc_submission_date'),
            'ten_percent_exit_date' => __('resources/custom/strings.export.ten_percent_exit_date'),
            'rial_return_date' => __('resources/custom/strings.export.rial_return_date'),
            'clearance_status' => __('resources/custom/strings.export.clearance_status'),
            'clearance_status_english' => __('resources/custom/strings.export.clearance_status_english'),
            'bank_guarantee_status' => __('resources/custom/strings.export.bank_guarantee_status'),
            'bank_guarantee_status_english' => __('resources/custom/strings.export.bank_guarantee_status_english'),
            'commitment_status' => __('resources/custom/strings.export.commitment_status'),
            'commitment_status_english' => __('resources/custom/strings.export.commitment_status_english'),
            'notes' => __('resources/custom/strings.export.notes'),
            'creator' => __('resources/custom/strings.export.creator'),
            'updater' => __('resources/custom/strings.export.updater'),
            'created_at' => __('resources/custom/strings.export.created_at'),
            'updated_at' => __('resources/custom/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function parentRow(Custom $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['custom_no'] = static::plainText($record->custom_no);
        $values['declaration_no'] = static::plainText($record->declaration_no);
        $values['contract_no'] = static::plainText($record->contract_no);
        $values['shipment_no'] = (string) ($record->shipment?->shipment_no ?? '');
        $values['registered_order'] = (string) ($record->registeredOrder?->ro_number ?? '');
        $values['clearance_type'] = $record->clearance_type
            ? (__('resources/custom/strings.general.clearance_types')[$record->clearance_type] ?? $record->clearance_type)
            : '';
        $values['commitment_balance'] = static::numberValue($record->commitment_balance);
        $values['clearance_date'] = static::jalaliDate($record->clearance_date);
        $values['doc_submission_date'] = static::jalaliDate($record->doc_submission_date);
        $values['ten_percent_exit_date'] = static::jalaliDate($record->ten_percent_exit_date);
        $values['rial_return_date'] = static::jalaliDate($record->rial_return_date);
        $values['clearance_status'] = $record->clearanceStatus?->getLocalizedNameAttribute() ?? '';
        $values['clearance_status_english'] = (string) ($record->clearanceStatus?->english_name ?? '');
        $values['bank_guarantee_status'] = $record->bankGuaranteeStatus?->getLocalizedNameAttribute() ?? '';
        $values['bank_guarantee_status_english'] = (string) ($record->bankGuaranteeStatus?->english_name ?? '');
        $values['commitment_status'] = $record->commitmentStatus?->getLocalizedNameAttribute() ?? '';
        $values['commitment_status_english'] = (string) ($record->commitmentStatus?->english_name ?? '');
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
