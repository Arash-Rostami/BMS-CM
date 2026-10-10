<?php

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SharedUserIdsCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, int> | null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (! isset($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return array_map('intval', is_array($decoded) ? $decoded : []);
    }

    /**
     * @param  array<int, mixed> | null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $ids = array_filter(array_map('intval', Arr::wrap($value)), fn (int $id): bool => $id > 0);

        return json_encode(array_values(array_unique($ids)));
    }
}
