<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\ShipmentResource;
use Filament\Actions\CreateAction;

class ListShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([ShipmentResource::getDeskReferenceHeaderAction()]),
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
