<?php

namespace App\Filament\Resources\Master\DepartmentResource\Exports;

use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\Writer;

class DepartmentExporter
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
            'id' => __('resources/department/strings.export.id'),
            'code' => __('resources/department/strings.export.code'),
            'name' => __('resources/department/strings.export.name'),
            'english_name' => __('resources/department/strings.export.english_name'),
            'description' => __('resources/department/strings.export.description'),
            'is_active' => __('resources/department/strings.export.is_active'),
            'creator' => __('resources/department/strings.export.creator'),
            'updater' => __('resources/department/strings.export.updater'),
            'created_at' => __('resources/department/strings.export.created_at'),
            'updated_at' => __('resources/department/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function row(Department $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['code'] = (string) $record->code;
        $values['name'] = static::plainText($record->name);
        $values['english_name'] = static::plainText($record->english_name);
        $values['description'] = static::plainText($record->description);
        $values['is_active'] = $record->is_active
            ? __('resources/department/strings.export.active')
            : __('resources/department/strings.export.inactive');
        $values['creator'] = (string) ($record->creator?->name ?? '');
        $values['updater'] = (string) ($record->updater?->name ?? '');
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
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    protected static function jalaliDate(mixed $date): string
    {
        return $date ? jdate($date)->format('Y-m-d') : '';
    }
}
