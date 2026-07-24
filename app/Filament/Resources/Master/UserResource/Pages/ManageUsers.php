<?php

namespace App\Filament\Resources\Master\UserResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\UserResource;
use Filament\Actions\CreateAction;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
