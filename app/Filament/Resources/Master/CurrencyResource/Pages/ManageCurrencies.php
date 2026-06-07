<?php

namespace App\Filament\Resources\Master\CurrencyResource\Pages;

use App\Filament\Resources\CurrencyResource;
use Filament\Actions\CreateAction;
use App\Filament\Pages\ManageRecords;

class ManageCurrencies extends ManageRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
