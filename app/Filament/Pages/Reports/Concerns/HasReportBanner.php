<?php

namespace App\Filament\Pages\Reports\Concerns;

use App\Services\Reports\Checks\CheckRegistry;
use Livewire\Attributes\Computed;

trait HasReportBanner
{
    /**
     * @return array{error: int, warning: int, info: int, total: int}
     */
    #[Computed]
    public function banner(): array
    {
        $counts = app(CheckRegistry::class)->counts(static::key(), $this->filterObject);

        return $counts + ['total' => array_sum($counts)];
    }
}
