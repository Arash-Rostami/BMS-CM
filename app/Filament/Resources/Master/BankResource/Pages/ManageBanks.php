<?php

namespace App\Filament\Resources\Master\BankResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\BankResource;
use Filament\Actions;
use App\Filament\Pages\ManageRecords;

class ManageBanks extends ManageRecords
{
    protected static string $resource = BankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
