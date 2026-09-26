<?php

namespace App\Services\Imports;

use App\Models\Status;
use App\Services\Country;
use Closure;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;

final class ImportColumnFactory
{
    public static function build(ImportColumnDefinition $definition): ImportColumn
    {
        $label = LocalizedMatcher::columnLabel($definition->name, $definition->labelKey);

        $column = ImportColumn::make($definition->name)
            ->label($label)
            ->exampleHeader($label)
            ->ignoreBlankState();

        if ($definition->labelKey) {
            $column = $column->guess(LocalizedMatcher::localizedGuesses($definition->labelKey));
        }

        return match (true) {
            $definition->relation !== null => self::applyRelationMatch($column, $definition),
            $definition->statusType !== null => self::applyStatusMatch($column, $definition),
            $definition->enumTranslationKey !== null => self::applyEnumMatch($column, $definition),
            $definition->isCountry => self::applyCountryMatch($column, $definition),
            $definition->isDate => self::applyDate($column),
            $definition->isNumber => self::applyNumber($column),
            default => self::applyPlainText($column),
        };
    }

    protected static function applyCountryMatch(ImportColumn $column, ImportColumnDefinition $definition): ImportColumn
    {
        $map = [];

        foreach ((new Country)->all() as $country) {
            $map[mb_strtolower($country['name'])] = $country['code'];
            $map[mb_strtolower($country['name_english'])] = $country['code'];
            $map[mb_strtolower($country['code'])] = $country['code'];
        }

        return $column
            ->castStateUsing(fn ($originalState) => self::castMappedState($map, $originalState, $definition->rejectOnMismatch()))
            ->rules([
                function (string $attribute, mixed $state, Closure $fail) use ($map): void {
                    if (blank($state)) {
                        return;
                    }

                    if (! in_array($state, $map, true)) {
                        $fail(__('resources/general/strings.import.invalid_option', ['value' => $state]));
                    }
                },
            ]);
    }

    protected static function castMappedState(array $map, mixed $originalState, bool $rejectOnMismatch): mixed
    {
        if (! is_string($originalState)) {
            return $originalState;
        }

        $key = mb_strtolower(trim($originalState));

        if (array_key_exists($key, $map)) {
            return $map[$key];
        }

        return $rejectOnMismatch ? $key : null;
    }

    protected static function applyRelationMatch(ImportColumn $column, ImportColumnDefinition $definition): ImportColumn
    {
        $resolve = function (string $state) use ($definition) {
            $query = $definition->model::query();

            foreach ($definition->resolveColumns as $index => $field) {
                $index === 0 ? $query->where($field, $state) : $query->orWhere($field, $state);
            }

            return $query->first();
        };

        if (! $definition->rejectOnMismatch()) {
            return $column->castStateUsing(function ($originalState) use ($resolve) {
                if (! is_string($originalState) || trim($originalState) === '') {
                    return null;
                }

                return $resolve(trim($originalState))?->getKey();
            });
        }

        $label = LocalizedMatcher::columnLabel($definition->name, $definition->labelKey);
        $column = $column->relationship($definition->relation, resolveUsing: fn (string $state) => $resolve($state));

        return $column->rules([
            function (string $attribute, mixed $state, Closure $fail) use ($column, $label): void {
                if (blank($state)) {
                    return;
                }

                if ($column->resolveRelatedRecord($state)) {
                    return;
                }

                $fail(__('resources/general/strings.import.lookup_not_found', ['label' => $label, 'value' => $state]));
            },
            'bail',
        ]);
    }

    protected static function applyStatusMatch(ImportColumn $column, ImportColumnDefinition $definition): ImportColumn
    {
        $type = $definition->statusType;

        return $column->castStateUsing(function ($originalState) use ($type, $definition) {
            if (! is_string($originalState) || trim($originalState) === '') {
                return null;
            }

            $value = trim($originalState);
            $status = Status::where('english_type', $type)
                ->where(fn ($query) => $query->where('english_name', $value)->orWhere('name', $value))
                ->first();

            if (! $status) {
                if (! $definition->rejectOnMismatch()) {
                    return null;
                }

                throw new RowImportFailedException(__('resources/general/strings.import.status_not_found', [
                    'type' => $type,
                    'value' => $value,
                ]));
            }

            return $status->id;
        });
    }

    protected static function applyEnumMatch(ImportColumn $column, ImportColumnDefinition $definition): ImportColumn
    {
        $map = LocalizedMatcher::enumMap($definition->enumTranslationKey);

        return $column
            ->castStateUsing(fn ($originalState) => self::castMappedState($map, $originalState, $definition->rejectOnMismatch()))
            ->rules([
                function (string $attribute, mixed $state, Closure $fail) use ($map): void {
                    if (blank($state)) {
                        return;
                    }

                    if (! in_array($state, $map, true)) {
                        $fail(__('resources/general/strings.import.invalid_option', ['value' => $state]));
                    }
                },
            ]);
    }

    protected static function applyDate(ImportColumn $column): ImportColumn
    {
        return $column->castStateUsing(function ($originalState, array $options) {
            if (! is_string($originalState) || trim($originalState) === '') {
                return null;
            }

            $folded = LocalizedMatcher::foldDigits(trim($originalState));
            $format = $options['date_format'] ?? 'Y-m-d';
            $jalali = (bool) ($options['jalali'] ?? false);
            $date = static::parseStrictDate($folded, $format, $jalali);

            if (! $date) {
                throw new RowImportFailedException(__('resources/general/strings.import.invalid_date', ['value' => $originalState]));
            }

            return $date->format('Y-m-d');
        });
    }

    protected static function parseStrictDate(string $value, string $format, bool $jalali): ?\Carbon\Carbon
    {
        $pattern = match ($format) {
            'Y-m-d' => '/^(?<y>\d{4})-(?<m>\d{1,2})-(?<d>\d{1,2})$/',
            'd/m/Y' => '/^(?<d>\d{1,2})\/(?<m>\d{1,2})\/(?<y>\d{4})$/',
            'm/d/Y' => '/^(?<m>\d{1,2})\/(?<d>\d{1,2})\/(?<y>\d{4})$/',
            default => null,
        };

        if (! $pattern || ! preg_match($pattern, $value, $matches)) {
            return null;
        }

        $year = (int) $matches['y'];
        $month = (int) $matches['m'];
        $day = (int) $matches['d'];

        if ($jalali) {
            if (! \Morilog\Jalali\CalendarUtils::isValidateJalaliDate($year, $month, $day)) {
                return null;
            }

            return \Carbon\Carbon::instance(\Morilog\Jalali\CalendarUtils::toGregorianDate($year, $month, $day));
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return \Carbon\Carbon::create($year, $month, $day);
    }

    protected static function applyNumber(ImportColumn $column): ImportColumn
    {
        return $column
            ->castStateUsing(fn ($originalState) => is_string($originalState)
                ? LocalizedMatcher::foldDigits(trim($originalState))
                : $originalState)
            ->rules([
                function (string $attribute, mixed $state, Closure $fail): void {
                    if (blank($state)) {
                        return;
                    }

                    if (! preg_match('/^-?\d+(\.\d+)?$/', (string) $state)) {
                        $fail(__('resources/general/strings.import.invalid_number', ['value' => $state]));
                    }
                },
            ]);
    }

    protected static function applyPlainText(ImportColumn $column): ImportColumn
    {
        return $column->castStateUsing(fn ($originalState) => is_string($originalState)
            ? LocalizedMatcher::foldDigits(trim($originalState))
            : $originalState);
    }
}
