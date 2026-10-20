<?php

namespace App\Filament\Pages\Reports\InTransit;

use App\Filament\Pages\Reports\Widgets\ReportAttentionWidget;

class InTransitAttentionWidget extends ReportAttentionWidget
{
    protected static function report(): string
    {
        return 'in_transit';
    }
}
