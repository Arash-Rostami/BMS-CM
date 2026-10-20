<?php

namespace App\Services\Reports\Support;

use League\Csv\EscapeFormula;
use League\Csv\Writer;

class ReportCsvWriter
{
    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $labels
     */
    public static function write(iterable $rows, array $labels, string $path): int
    {
        $stream = fopen($path, 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        $csv = Writer::from($stream);
        $csv->insertOne(array_values($labels));

        $count = 0;

        foreach ($rows as $row) {
            $csv->insertOne(static::record($row, array_keys($labels)));
            $count++;
        }

        fclose($stream);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    protected static function record(array $row, array $keys): array
    {
        return array_map(fn (string $key) => static::safe(static::text($row[$key] ?? null)), $keys);
    }

    protected static function safe(string $value): string
    {
        return is_numeric($value) ? $value : (new EscapeFormula)->escapeRecord([$value])[0];
    }

    protected static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_array($value) => implode(' | ', array_map(fn (mixed $item) => static::text($item), $value)),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
