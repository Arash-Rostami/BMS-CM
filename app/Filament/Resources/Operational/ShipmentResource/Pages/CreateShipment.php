<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\Operational\ShipmentResource\Traits\HandlesDocumentChecklistForm;
use App\Filament\Resources\Operational\ShipmentResource\Traits\PrepareShipmentFromRegisteredOrder;
use App\Filament\Resources\Operational\ShipmentResource\Traits\SyncsDocumentChecklist;
use App\Filament\Resources\ShipmentResource;
use App\Models\Shipment;

class CreateShipment extends CreateRecord
{
    use HandlesDocumentChecklistForm, PrepareShipmentFromRegisteredOrder, SyncsDocumentChecklist;

    protected static string $resource = ShipmentResource::class;

    public function afterFill(): void
    {
        if (request()->has('registered_order_id')) {
            self::afterFillFromRegisteredOrder();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ShipmentResource::applyInitialStatusOnCreate($data, 'status_id', Shipment::TYPE_SHIPMENT_STATUS);
        $data = ShipmentResource::applyInitialStatusOnCreate($data, 'container_status_id', Shipment::TYPE_CONTAINER_STATUS);
        $data = ShipmentResource::applyInitialStatusOnCreate($data, 'operation_status_id', Shipment::TYPE_OPERATION_STATUS);
        $data = ShipmentResource::applyInitialStatusOnCreate($data, 'shipment_status_id', Shipment::TYPE_TRACKING_STATUS);

        return ShipmentResource::applyInitialStatusOnCreate($data, 'doc_status_id', Shipment::TYPE_DOC_STATUS);
    }
}
