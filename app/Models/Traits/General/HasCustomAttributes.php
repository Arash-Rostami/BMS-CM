<?php

namespace App\Models\Traits\General;

use App\Models\EntityAttribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCustomAttributes
{
    public function customAttributes(): MorphMany
    {
        return $this->morphMany(EntityAttribute::class, 'entity');
    }

    public function extraAttributes(): MorphMany
    {
        return $this->morphMany(EntityAttribute::class, 'entity');
    }

    public function getCustomAttributesMap(): array
    {
        return $this->customAttributes()
            ->pluck('value', 'key')
            ->map(fn($v) => is_string($v) ? $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE))
            ->toArray();
    }
}
