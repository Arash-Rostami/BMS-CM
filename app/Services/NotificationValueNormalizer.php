<?php

namespace App\Services;

use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationValueNormalizer
{
    /**
     * @var array<string, array<string, string>>
     */
    private static array $types = [];

    public static function normalize(mixed $value, string $table, string $column): ?string
    {
        $value = $value instanceof BackedEnum ? $value->value : $value;
        $value = is_bool($value) ? (int) $value : $value;

        if ($value === null || (! is_scalar($value) && ! $value instanceof DateTimeInterface)) {
            return null;
        }

        return match (self::typeOf($table, $column)) {
            'decimal', 'numeric', 'float', 'double', 'real' => self::decimal($value),
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'year' => self::decimal($value),
            'date' => self::date($value, 'Y-m-d'),
            'datetime', 'timestamp' => self::date($value, 'Y-m-d H:i:s'),
            'bool', 'boolean' => self::boolean($value),
            default => self::text($value),
        };
    }

    public static function matches(mixed $candidate, string $normalized, string $table, string $column, ?string $previous = null, bool $isUpdate = false): bool
    {
        $day = is_string($candidate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($candidate)) === 1 && in_array(self::typeOf($table, $column), ['datetime', 'timestamp'], true)
            ? trim($candidate).' '
            : null;

        if ($day === null) {
            return self::normalize($candidate, $table, $column) === $normalized;
        }

        return str_starts_with($normalized, $day) && ! ($isUpdate && $previous !== null && str_starts_with($previous, $day));
    }

    public static function flush(): void
    {
        self::$types = [];
    }

    private static function typeOf(string $table, string $column): string
    {
        self::$types[$table] ??= self::loadTypes($table);

        return self::$types[$table][$column] ?? 'string';
    }

    /**
     * @return array<string, string>
     */
    private static function loadTypes(string $table): array
    {
        try {
            return collect(Schema::getColumns($table))
                ->mapWithKeys(fn (array $column): array => [$column['name'] => strtolower($column['type_name'])])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private static function decimal(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $text = is_float($value) ? sprintf('%.10F', $value) : (string) $value;

        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return in_array($text, ['-0', ''], true) ? '0' : ltrim($text, '+');
    }

    private static function boolean(mixed $value): ?string
    {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed === null ? null : ($parsed ? '1' : '0');
    }

    private static function date(mixed $value, string $format): ?string
    {
        try {
            return ($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value))->format($format);
        } catch (Throwable) {
            return null;
        }
    }

    private static function text(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value;
    }
}
