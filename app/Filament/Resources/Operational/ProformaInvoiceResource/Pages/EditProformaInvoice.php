<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ProformaInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProformaInvoice extends EditRecord
{
    protected static string $resource = ProformaInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
