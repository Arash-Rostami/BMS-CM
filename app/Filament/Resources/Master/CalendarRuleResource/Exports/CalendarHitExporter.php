<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Exports;

use App\Models\CalendarHit;
use App\Services\Calendar\CalendarModules;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class CalendarHitExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with('rule')
            ->orderBy('id')
            ->get();

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
            'id' => __('resources/calendarRule/strings.export.id'),
            'rule' => __('resources/calendarRule/strings.export.rule'),
            'module' => __('resources/calendarRule/strings.export.module'),
            'item' => __('resources/calendarRule/strings.export.item'),
            'event_date' => __('resources/calendarRule/strings.export.event_date'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function row(CalendarHit $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['rule'] = static::plainText($record->rule?->name ?? '');
        $values['module'] = static::plainText(CalendarModules::label($record->subject_type));
        $values['item'] = static::plainText($record->label ?? '');
        $values['event_date'] = static::jalaliDate($record->event_date);

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
