<?php

namespace App\Filament\Pages\Reports\FxSupply;

use App\Filament\Pages\Reports\Widgets\ReportAttentionWidget;

class FxSupplyAttentionWidget extends ReportAttentionWidget
{
    protected static function report(): string
    {
        return 'fx_supply';
    }
}
