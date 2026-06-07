<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Pages;

use App\Filament\Resources\ShipmentResource;
use Filament\Actions\CreateAction;
use App\Filament\Pages\ListRecords;

class ListShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
