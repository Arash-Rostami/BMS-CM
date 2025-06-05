<?php

namespace App\Models\Traits\Product;


trait RollSheetEstimator
{
    public function determineRollOrSheetType(): ?string
    {
        $attrs = (array)$this->getAttributeValue('attributes');


        if ($this->category && !$this->category->ancestors->pluck('id')->contains(5)) {
            return null;
        }

        if (collect($attrs)->contains(fn($v) => str_contains($v, '*'))) {
            return 'Sheet';
        }

        if (collect($attrs)->contains(fn($v) => str_contains(strtolower($v), 'cm'))) {
            return 'Roll';
        }

        return null;
    }
}
