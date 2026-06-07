<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions;
use App\Filament\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
