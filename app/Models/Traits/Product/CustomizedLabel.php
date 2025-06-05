<?php

namespace App\Models\Traits\Product;

trait CustomizedLabel
{
    public function getCustomizedLabelAttribute(): string
    {
        $segments = array_filter([
            $this->code,
            method_exists($this, 'getLocalizedNameAttribute') && !empty($this->getLocalizedNameAttribute())
                ? $this->getLocalizedNameAttribute()
                : (!empty($this->name) ? $this->name : null),
        ]);

        $attrs = $this->getAttribute('attributes');
        $attrsString = (is_array($attrs) && count($attrs)) ? ' (' . implode(', ', $attrs) . ')' : '';

        return implode(' - ', $segments) . $attrsString;
    }
}
