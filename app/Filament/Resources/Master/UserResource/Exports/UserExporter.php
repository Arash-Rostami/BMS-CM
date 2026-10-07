<?php

namespace App\Filament\Resources\Master\UserResource\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class UserExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['department'])
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
            'id' => __('resources/user/strings.export.id'),
            'name' => __('resources/user/strings.export.name'),
            'phone' => __('resources/user/strings.export.phone'),
            'email' => __('resources/user/strings.export.email'),
            'company' => __('resources/user/strings.export.company'),
            'department' => __('resources/user/strings.export.department'),
            'position' => __('resources/user/strings.export.position'),
            'role' => __('resources/user/strings.export.role'),
            'status' => __('resources/user/strings.export.status'),
            'ip' => __('resources/user/strings.export.ip'),
            'last_log_in' => __('resources/user/strings.export.last_log_in'),
            'last_log_out' => __('resources/user/strings.export.last_log_out'),
            'created_at' => __('resources/user/strings.export.created_at'),
            'updated_at' => __('resources/user/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function row(User $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['name'] = static::plainText($record->name);
        $values['phone'] = (string) ($record->phone ?? '');
        $values['email'] = (string) ($record->email ?? '');
        $values['company'] = static::plainText($record->company);
        $values['department'] = static::plainText($record->department?->name);
        $values['position'] = (string) ($record->position ?? '');
        $values['role'] = (string) ($record->role ?? '');
        $values['status'] = (string) ($record->status ?? '');
        $values['ip'] = (string) ($record->ip ?? '');
        $values['last_log_in'] = static::jalaliDateTime($record->last_log_in);
        $values['last_log_out'] = static::jalaliDateTime($record->last_log_out);
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

    protected static function jalaliDateTime(mixed $date): string
    {
        return $date ? jdate($date)->format('Y-m-d H:i:s') : '';
    }
}
