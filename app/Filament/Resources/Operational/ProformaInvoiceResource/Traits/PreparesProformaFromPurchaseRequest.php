<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Services\CodeGenerator;

trait PreparesProformaFromPurchaseRequest
{
    public function afterFill(): void
    {
        if (request()->has('purchase_request_id')) {
            $this->form->fill([
                'purchaseRequests' => [request()->query('purchase_request_id')],
                'invoice_no' => CodeGenerator::generate(),
                'invoice_date' => now()->toDateString(),
                'validity_date' => now()->addWeek()->toDateString(),
            ]);
        }
    }
}
