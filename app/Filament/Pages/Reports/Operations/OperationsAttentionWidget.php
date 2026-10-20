<?php

namespace App\Filament\Pages\Reports\Operations;

use App\Filament\Pages\Reports\Widgets\ReportAttentionWidget;

class OperationsAttentionWidget extends ReportAttentionWidget
{
    protected static function report(): string
    {
        return 'operations';
    }
}
