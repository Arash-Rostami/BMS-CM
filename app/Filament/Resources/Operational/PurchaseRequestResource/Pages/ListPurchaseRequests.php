<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\PurchaseRequestResource;
use Filament\Actions\CreateAction;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([PurchaseRequestResource::getDeskReferenceHeaderAction()]),
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
