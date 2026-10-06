<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions\CreateAction;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([PurchaseOrderResource::getDeskReferenceHeaderAction()]),
            PurchaseOrderResource::getImportAction(),
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
