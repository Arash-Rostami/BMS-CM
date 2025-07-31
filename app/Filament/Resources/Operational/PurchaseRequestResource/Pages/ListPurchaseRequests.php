<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
