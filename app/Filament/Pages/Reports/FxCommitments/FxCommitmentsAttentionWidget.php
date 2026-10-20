<?php

namespace App\Filament\Pages\Reports\FxCommitments;

use App\Filament\Pages\Reports\Widgets\ReportAttentionWidget;

class FxCommitmentsAttentionWidget extends ReportAttentionWidget
{
    protected static function report(): string
    {
        return 'fx_commitments';
    }
}
