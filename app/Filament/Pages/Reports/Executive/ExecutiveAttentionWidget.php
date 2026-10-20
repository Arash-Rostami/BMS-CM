<?php

namespace App\Filament\Pages\Reports\Executive;

use App\Filament\Pages\Reports\Widgets\ReportAttentionWidget;

class ExecutiveAttentionWidget extends ReportAttentionWidget
{
    protected static function report(): string
    {
        return 'executive';
    }
}
