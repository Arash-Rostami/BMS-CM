<?php

namespace App\Filament\Resources\Master\CurrencyResource\Exports;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class CurrencyExporter
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
            'id' => __('resources/currency/strings.export.id'),
            'name' => __('resources/currency/strings.export.name'),
            'english_name' => __('resources/currency/strings.export.english_name'),
            'description' => __('resources/currency/strings.export.description'),
            'is_active' => __('resources/currency/strings.export.is_active'),
            'creator' => __('resources/currency/strings.export.creator'),
            'updater' => __('resources/currency/strings.export.updater'),
            'created_at' => __('resources/currency/strings.export.created_at'),
            'updated_at' => __('resources/currency/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function row(Currency $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['name'] = static::plainText($record->name);
        $values['english_name'] = static::plainText($record->english_name);
        $values['description'] = static::plainText($record->description);
        $values['is_active'] = $record->is_active
            ? __('resources/currency/strings.export.active')
            : __('resources/currency/strings.export.inactive');
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
