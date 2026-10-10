<?php

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OutsideEmailsCast implements CastsAttributes
{
    public const MAX_ITEMS = 10;

    public const MAX_LENGTH = 254;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string> | null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (! isset($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return self::clean(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $emails = self::clean($value);

        return $emails === [] ? null : json_encode($emails);
    }

    /**
     * @return array<int, string>
     */
    public static function clean(mixed $value): array
    {
        return collect(Arr::wrap($value))
            ->filter(fn (mixed $email): bool => is_string($email))
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->filter(fn (string $email): bool => self::isValid($email))
            ->unique()
            ->take(self::MAX_ITEMS)
            ->values()
            ->all();
    }

    public static function isValid(string $email): bool
    {
        return strlen($email) <= self::MAX_LENGTH
            && ! preg_match('/[\s"<>(),;:\\\\\x00-\x1f]/', $email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
