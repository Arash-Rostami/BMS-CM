<?php

namespace App\Models\Traits\Custom;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasComputedAttributes
{
    public function clearanceAgingDays(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->doc_submission_date) {
                    return null;
                }

                return (int) abs($this->doc_submission_date->diffInDays($this->clearance_date ?? now()));
            }
        );
    }
}
