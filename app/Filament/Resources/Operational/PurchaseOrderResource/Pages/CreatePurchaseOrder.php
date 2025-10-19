<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Pages;

use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromPurchaseRequest;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseRequest;
use App\Services\CodeGenerator;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    use PreparesPurchaseOrderFromPurchaseRequest;

    protected static string $resource = PurchaseOrderResource::class;

}
