<?php

namespace App\Models\Traits\Company;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Closure;

trait HasCustomSorts
{
    protected static function getSellerCompanySort(string $foreignKeyOnParentTable): Closure
    {
        return function (Builder $query, string $direction) use ($foreignKeyOnParentTable) {

            $nameColumn = app()->getLocale() == 'fa' ? 'name' : 'english_name';

            $subQuery = Company::select($nameColumn)
                ->whereColumn('companies.id', $foreignKeyOnParentTable)
                ->where('is_active', 1)
                ->ofAnyType(Company::TYPE_SERVICE_ALL_SELLERS)
                ->limit(1);

            return $query->orderBy($subQuery, $direction);
        };
    }
}
