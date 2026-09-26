<?php

namespace App\Services\Imports;

use Filament\Actions\Imports\ImportColumn;

final class ImportDefaultsShared
{
    public static function extraAttributeColumns(int $pairs = 5): array
    {
        $columns = [];

        for ($i = 1; $i <= $pairs; $i++) {
            $keyLabel = __('resources/general/strings.extra_attributes.key').' '.$i;
            $valueLabel = __('resources/general/strings.extra_attributes.value').' '.$i;

            $columns[] = ImportColumn::make("extra_key_{$i}")
                ->label("{$keyLabel} (extra_key_{$i})")
                ->exampleHeader("{$keyLabel} (extra_key_{$i})")
                ->ignoreBlankState()
                ->castStateUsing(fn ($originalState) => is_string($originalState) ? trim($originalState) : $originalState);

            $columns[] = ImportColumn::make("extra_value_{$i}")
                ->label("{$valueLabel} (extra_value_{$i})")
                ->exampleHeader("{$valueLabel} (extra_value_{$i})")
                ->ignoreBlankState()
                ->castStateUsing(fn ($originalState) => is_string($originalState) ? trim($originalState) : $originalState);
        }

        return $columns;
    }

    public static function extraAttributeMapFromData(array $data, int $pairs = 5): array
    {
        $map = [];

        for ($i = 1; $i <= $pairs; $i++) {
            $key = $data["extra_key_{$i}"] ?? null;

            if (blank($key)) {
                continue;
            }

            $map[$key] = $data["extra_value_{$i}"] ?? '';
        }

        return $map;
    }
}
