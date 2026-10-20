<?php

namespace App\Filament\Pages\Reports\Requester;

use App\Filament\Pages\Reports\Widgets\ReportAttentionWidget;

class RequesterAttentionWidget extends ReportAttentionWidget
{
    protected static function report(): string
    {
        return 'requester';
    }
}
