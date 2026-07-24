<?php

namespace App\Filament\Resources\Master\BankResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\BankResource;
use Filament\Actions\CreateAction;

class ManageBanks extends ManageRecords
{
    protected static string $resource = BankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
