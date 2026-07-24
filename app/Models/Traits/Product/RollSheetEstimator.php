<?php

namespace App\Models\Traits\Product;

use Illuminate\Database\Eloquent\Builder;

trait RollSheetEstimator
{
    public function determineRollOrSheetType(): ?string
    {
        $attrs = (array) $this->getAttributeValue('attributes');

        if ($this->category && ! $this->category->ancestors->pluck('id')->contains(5)) {
            return null;
        }

        if (collect($attrs)->contains(fn ($v) => str_contains($v, '*'))) {
            return 'Sheet';
        }

        if (collect($attrs)->contains(fn ($v) => str_contains(strtolower($v), 'cm'))) {
            return 'Roll';
        }

        return null;
    }

    public static function applyRollSheetSearch(Builder $query, ?string $search, ?string $filterValue = null): Builder
    {
        $searchTerm = $search ? strtolower($search) : '';
        $filterType = $filterValue ? strtolower($filterValue) : '';

        if ($filterType === 'sheet' || $filterType === 'roll') {
            return $filterType === 'sheet'
                ? $query->whereIsSheet()
                : $query->whereIsRoll();
        }

        if ($searchTerm) {
            if (str_contains($searchTerm, 'sheet')) {
                return $query->whereIsSheet();
            }
            if (str_contains($searchTerm, 'roll')) {
                return $query->whereIsRoll();
            }
        }

        return $query;
    }
}
