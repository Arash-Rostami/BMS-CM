<?php

namespace App\Models\Traits\Category;

use Illuminate\Support\Facades\Cache;

trait NestedOptions
{
    public function getCachedHierarchy(string $cacheKeyBase = 'categories.hierarchy', int $ttl = 5): array
    {
        $locale = app()->getLocale();
        $cacheKey = "{$cacheKeyBase}.{$locale}";

        return Cache::remember($cacheKey, now()->addMinutes($ttl), function () {
            $nameCol = $this->localeColumn();

            return $this->active()
                ->withoutTrashed()
                ->select('id', 'parent_id', $nameCol)
                ->whereNotNull($nameCol)
                ->get()
                ->groupBy(fn($item) => $item->parent_id ?? 0)
                ->map(fn($group) => $group->pluck($nameCol, 'id')->toArray())
                ->all();
        });
    }

}
