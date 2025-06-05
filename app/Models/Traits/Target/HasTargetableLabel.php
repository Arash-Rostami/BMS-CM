<?php

namespace App\Models\Traits\Target;


use App\Models\Category;

trait HasTargetableLabel
{

    public function getTargetableLabelAttribute(): string
    {
        if (!$this->relationLoaded('targetable')) {
            $this->load('targetable');
        }

        if (!$this->targetable) {
            return '-';
        }

        return $this->targetable instanceof Category
            ? $this->targetable->getLocalizedNameAttribute()
            : sprintf('Sprint: %s - %s', $this->targetable->code, $this->targetable->getLocalizedNameAttribute());
    }
}
