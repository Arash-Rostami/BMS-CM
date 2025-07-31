<?php

namespace App\Models\Traits\Status;

use Illuminate\Database\Eloquent\Collection;

trait StatusFinder
{
    public static function findBy(string $type, ?string $name = null): static|Collection|null
    {
        $query = static::where('english_type', $type);

        if ($name) {
            $query->where('english_name', $name);
            return $query->first();
        }

        return $query->get();
    }
}
