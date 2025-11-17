<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Pages;

use App\Filament\Resources\RegisteredOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegisteredOrders extends ListRecords
{
    protected static string $resource = RegisteredOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
