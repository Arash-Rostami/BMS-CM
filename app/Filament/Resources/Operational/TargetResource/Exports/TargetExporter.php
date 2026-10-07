<?php

namespace App\Filament\Resources\Operational\TargetResource\Exports;

use App\Models\Target;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class TargetExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['targetable', 'creator', 'updater'])
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
            'id' => __('resources/target/strings.export.id'),
            'targetable' => __('resources/target/strings.export.targetable'),
            'year' => __('resources/target/strings.export.year'),
            'start_from' => __('resources/target/strings.export.start_from'),
            'end_in' => __('resources/target/strings.export.end_in'),
            'quantity' => __('resources/target/strings.export.quantity'),
            'amount' => __('resources/target/strings.export.amount'),
            'achieved_quantity' => __('resources/target/strings.export.achieved_quantity'),
            'achieved_amount' => __('resources/target/strings.export.achieved_amount'),
            'metrics' => __('resources/target/strings.export.metrics'),
            'description' => __('resources/target/strings.export.description'),
            'tags' => __('resources/target/strings.export.tags'),
            'status' => __('resources/target/strings.export.status'),
            'creator' => __('resources/target/strings.export.creator'),
            'updater' => __('resources/target/strings.export.updater'),
            'created_at' => __('resources/target/strings.export.created_at'),
            'updated_at' => __('resources/target/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function row(Target $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['targetable'] = static::plainText($record->targetable_label);
        $values['year'] = (string) $record->year;
        $values['start_from'] = static::jalaliDate($record->start_from);
        $values['end_in'] = static::jalaliDate($record->end_in);
        $values['quantity'] = static::numberValue($record->quantity);
        $values['amount'] = static::numberValue($record->amount);
        $values['achieved_quantity'] = static::numberValue($record->achieved_quantity);
        $values['achieved_amount'] = static::numberValue($record->achieved_amount);
        $values['metrics'] = static::plainText($record->metrics);
        $values['description'] = static::plainText($record->description);
        $values['tags'] = static::plainText(implode(', ', Arr::wrap($record->tags)));
        $values['status'] = __('resources/target/strings.status.'.$record->status);
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
