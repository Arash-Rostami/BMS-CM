<?php

namespace App\Filament\Resources\Operational\CustomResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Traits\PrepareCustomFromShipment;

class CreateCustom extends CreateRecord
{
    use PrepareCustomFromShipment;

    protected static string $resource = CustomResource::class;

    public function afterFill(): void
    {
        if (request()->has('shipment_id')) {
            self::afterFillFromShipment();
        }
    }
}
