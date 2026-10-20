<?php

namespace App\Services\Reports\Support;

use Illuminate\Database\Eloquent\Builder;

final class ReportDates
{
    /**
     * @param  array<string, string>  $columns  basis key => fully-qualified column; the whitelist of one report
     */
    public static function apply(Builder $query, ReportFilters $filters, array $columns, string $default): Builder
    {
        if ($filters->dateFrom === null && $filters->dateUntil === null) {
            return $query;
        }

        $column = $columns[$filters->dateBasis ?? ''] ?? $columns[$default] ?? reset($columns);

        return $query->where(fn (Builder $range): Builder => $range
            ->where(fn (Builder $within): Builder => $within
                ->when($filters->dateFrom, fn (Builder $q) => $q->whereDate($column, '>=', $filters->dateFrom->toDateString()))
                ->when($filters->dateUntil, fn (Builder $q) => $q->whereDate($column, '<=', $filters->dateUntil->toDateString())))
            ->when($filters->includeUndated, fn (Builder $q) => $q->orWhereNull($column)));
    }
}
