<?php

namespace App\Filament\Resources\Master\StatusResource\Exports;

use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class StatusExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['creator', 'updater'])
            ->orderBy('id')
            ->lazy();

        $stream = fopen($absolutePath, 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        $csv = Writer::from($stream);
        $csv->insertOne(array_values($labels));

        $rows = 0;

        foreach ($records as $record) {
            $csv->insertOne(static::row($record, $labels));
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
            'id' => __('resources/status/strings.export.id'),
            'name' => __('resources/status/strings.export.name'),
            'english_name' => __('resources/status/strings.export.english_name'),
            'type' => __('resources/status/strings.export.type'),
            'english_type' => __('resources/status/strings.export.english_type'),
            'creator' => __('resources/status/strings.export.creator'),
            'updater' => __('resources/status/strings.export.updater'),
            'created_at' => __('resources/status/strings.export.created_at'),
            'updated_at' => __('resources/status/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function row(Status $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['name'] = static::plainText($record->name);
        $values['english_name'] = static::plainText($record->english_name);
        $values['type'] = static::plainText($record->type);
        $values['english_type'] = static::plainText($record->english_type);
        $values['creator'] = static::plainText($record->creator?->name ?? '');
        $values['updater'] = static::plainText($record->updater?->name ?? '');
        $values['created_at'] = static::jalaliDate($record->created_at);
        $values['updated_at'] = static::jalaliDate($record->updated_at);

        return array_values($values);
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
