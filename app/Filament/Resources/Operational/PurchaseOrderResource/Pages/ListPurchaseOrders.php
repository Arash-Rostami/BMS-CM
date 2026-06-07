<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions;
use App\Filament\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
