<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use App\Models\RegisteredOrder;
use App\Services\CodeGenerator;

trait PrepareBankProfileFromRegisteredOrder
{
    public function afterFillFromRegisteredOrder(): void
    {
        if (request()->has('registered_order_id')) {
            $registeredOrderId = request()->query('registered_order_id');
            $registeredOrder = RegisteredOrder::find($registeredOrderId);

            if ($registeredOrder) {
                $this->form->fill([
                    'bp_number' => CodeGenerator::generate('bp_number') ?? null,
                    'registered_order_id' => $registeredOrder->id,
                    'company_id' => $registeredOrder->buyer_id,
                    'purchased_currency_id' => $registeredOrder->currency_id,
                ]);
            }
        }
    }
}
