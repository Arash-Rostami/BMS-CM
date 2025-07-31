<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Pages;

use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\PreparesProformaFromPurchaseRequest;
use App\Filament\Resources\ProformaInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProformaInvoice extends CreateRecord
{
    use PreparesProformaFromPurchaseRequest;

    protected static string $resource = ProformaInvoiceResource::class;
}
