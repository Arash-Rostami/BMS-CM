<?php

namespace App\Filament\Traits;

trait PrefillsTableSearch
{
    public function mount(): void
    {
        parent::mount();

        if ($term = request()->query('search')) {
            $this->tableSearch = $term;
        }
    }
}
