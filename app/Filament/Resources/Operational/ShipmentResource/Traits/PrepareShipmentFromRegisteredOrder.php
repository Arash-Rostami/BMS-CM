<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\CodeGenerator;

trait PrepareShipmentFromRegisteredOrder
{
    public function afterFillFromRegisteredOrder(): void
    {
        if (request()->has('registered_order_id')) {
            $registeredOrderId = request()->query('registered_order_id');
            $registeredOrder = RegisteredOrder::find($registeredOrderId);

            if ($registeredOrder) {
                $partData = Shipment::getPartData($registeredOrder->id, (string)$registeredOrder->contract_no);
                $defaultStatus = Status::findBy(Shipment::TYPE_SHIPMENT_STATUS, 'Processing')?->id;

                $this->form->fill([
                    'shipment_no' => CodeGenerator::generate('shipment_no') ?? null,
                    'registered_order_id' => $registeredOrder->id,
                    'company_id' => $registeredOrder->buyer_id,
                    'contract_no' => $registeredOrder->contract_no,
                    'part' => $partData['next'] ?? 1,
                    'status_id' => $defaultStatus
                ]);
            }
        }
    }
}
