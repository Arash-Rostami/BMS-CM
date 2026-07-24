<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\BankProfileResource;
use Filament\Actions\CreateAction;

class ListBankProfiles extends ListRecords
{
    protected static string $resource = BankProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([BankProfileResource::getDeskReferenceHeaderAction()]),
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
