<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Pages;

use App\Filament\Resources\Operational\ShipmentResource\Traits\HandlesDocumentChecklistForm;
use App\Filament\Resources\Operational\ShipmentResource\Traits\PrepareShipmentFromRegisteredOrder;
use App\Filament\Resources\Operational\ShipmentResource\Traits\SyncsDocumentChecklist;
use App\Filament\Resources\ShipmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShipment extends CreateRecord
{
    use HandlesDocumentChecklistForm, PrepareShipmentFromRegisteredOrder, SyncsDocumentChecklist;

    protected static string $resource = ShipmentResource::class;

    public function afterFill(): void
    {
        if (request()->has('registered_order_id')) self::afterFillFromRegisteredOrder();
    }
}
