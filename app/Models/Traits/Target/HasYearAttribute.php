<?php

namespace App\Models\Traits\Target;

use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Services\PersianCalendar;

trait HasYearAttribute
{
    protected function year(): Attribute
    {
        return Attribute::make(
            get: function (int $value): int {
                if (app()->isLocale('fa') && $value >= 1000 && $value <= 2000) {
                    return $value;
                }
                return app(PersianCalendar::class)->convertYear($value);
            },
        );
    }
}
