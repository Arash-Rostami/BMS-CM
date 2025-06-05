<?php

namespace App\Models\Traits\Category;

trait BreadCrumbs
{
    public function sortAncestors()
    {
        return $this->sortedAncestors()->map->getLocalizedNameAttribute()
            ->push($this->getLocalizedNameAttribute())
            ->implode(' » ');
    }
}
