<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Models\RegisteredOrder;
use App\Models\Custom;
use App\Models\Status;

trait PrepareCustomFromRegisteredOrder
{
    public function afterFillFromRegisteredOrder(): void
    {
        if (request()->has('registered_order_id')) {
            $registeredOrderId = request()->query('registered_order_id');
            $registeredOrder = RegisteredOrder::find($registeredOrderId);

            if ($registeredOrder) {
                // Set default status if exists
                $defaultClearanceStatus = Status::findBy(Custom::TYPE_CLEARANCE_STATUS, 'Pending')?->id;

                $this->form->fill([
                    'registered_order_id' => $registeredOrder->id,
                    'contract_no' => $registeredOrder->contract_no,
                    // If there is a shipment_no on the registered order context (if passed), we could fill it,
                    // otherwise leave blank or generate if needed.
                    'clearance_status_id' => $defaultClearanceStatus,
                ]);
            }
        }
    }
}
