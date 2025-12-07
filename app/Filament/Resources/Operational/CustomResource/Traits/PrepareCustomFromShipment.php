<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Models\Shipment;
use App\Models\Custom;
use App\Models\Status;
use App\Services\CodeGenerator;

trait PrepareCustomFromShipment
{
    public function afterFillFromShipment(): void
    {
        if (request()->has('shipment_id')) {
            $shipmentId = request()->query('shipment_id');
            $shipment = Shipment::find($shipmentId);

            if ($shipment) {
                $defaultClearanceStatus = Status::findBy(Custom::TYPE_CLEARANCE_STATUS, 'Pending')?->id;

                $this->form->fill([
                    'custom_no' => CodeGenerator::generate('custom_no'),
                    'shipment_id' => $shipment->id,
                    'registered_order_id' => $shipment->registered_order_id,
                    'contract_no' => $shipment->contract_no,
                    'clearance_status_id' => $defaultClearanceStatus,
                ]);
            }
        }
    }
}
