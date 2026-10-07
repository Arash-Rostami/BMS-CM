<?php

namespace App\Models\Traits\Target;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasMetricAttribute
{
    protected function metrics(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (! $value) {
                    return $value;
                }

                $key = 'resources/general/strings.metrics.'.$value;
                $label = __($key);

                return $label === $key ? $value : $label;
            },
            set: function ($value) {
                if (array_key_exists($value, __('resources/general/strings.metrics'))) {
                    return $value;
                }
                $code = array_search($value, __('resources/general/strings.metrics'), true);

                return $code !== false ? $code : $value;
            }
        );
    }
}
