<?php

namespace Tests\Support\Reports;

use App\Filament\Pages\Reports\Widgets\ReportAttentionWidget;

class StubAttentionWidget extends ReportAttentionWidget
{
    protected static function report(): string
    {
        return 'fx_supply';
    }
}
