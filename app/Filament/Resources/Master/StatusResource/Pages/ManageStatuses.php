<?php

namespace App\Filament\Resources\Master\StatusResource\Pages;

use App\Filament\Resources\StatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStatuses extends ManageRecords
{
    protected static string $resource = StatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
