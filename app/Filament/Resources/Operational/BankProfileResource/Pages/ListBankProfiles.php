<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Pages;

use App\Filament\Resources\BankProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankProfiles extends ListRecords
{
    protected static string $resource = BankProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
