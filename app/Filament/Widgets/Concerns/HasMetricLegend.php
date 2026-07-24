<?php

namespace App\Filament\Widgets\Concerns;

trait HasMetricLegend
{
    public function getLegend(): array
    {
        $key = static::LEGEND_KEY;

        return [
            'what' => __("resources/dashboard/strings.widgets.legend.{$key}.what"),
            'data' => __("resources/dashboard/strings.widgets.legend.{$key}.data"),
            'why' => __("resources/dashboard/strings.widgets.legend.{$key}.why"),
            'technical' => auth()->user()?->isAdmin() ? __("resources/dashboard/strings.widgets.legend.{$key}.technical") : null,
        ];
    }
}
