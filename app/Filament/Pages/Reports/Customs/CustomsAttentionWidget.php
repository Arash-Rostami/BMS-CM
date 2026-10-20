<?php

namespace App\Filament\Pages\Reports\Customs;

use App\Filament\Pages\Reports\Widgets\ReportAttentionWidget;

class CustomsAttentionWidget extends ReportAttentionWidget
{
    protected static function report(): string
    {
        return 'customs';
    }
}
