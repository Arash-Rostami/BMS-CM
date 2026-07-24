<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\RegisteredOrderResource;
use Filament\Actions\CreateAction;

class ListRegisteredOrders extends ListRecords
{
    protected static string $resource = RegisteredOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([RegisteredOrderResource::getDeskReferenceHeaderAction()]),
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
