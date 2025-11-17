<?php

namespace App\Filament\Resources\Operational\PaymentResource\Traits;

use Filament\Schemas\Components\Utilities\Get;


trait VisibilityCheck
{
    public static function isTargetSelected(Get $get): bool
    {
        return !empty($get('targetable_type')) && !empty($get('targetable_id'));
    }
}
