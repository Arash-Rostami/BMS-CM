<?php

namespace App\Filament\Resources\Operational\CustomResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Traits\HandleStatusMutation;
use App\Filament\Resources\Operational\CustomResource\Traits\PrepareCustomFromShipment;

class CreateCustom extends CreateRecord
{
    use HandleStatusMutation, PrepareCustomFromShipment;

    protected static string $resource = CustomResource::class;

    public function afterFill(): void
    {
        if (request()->has('shipment_id')) {
            self::afterFillFromShipment();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->assertCustomStatusTransitionsAllowed($data);

        return $data;
    }
}
