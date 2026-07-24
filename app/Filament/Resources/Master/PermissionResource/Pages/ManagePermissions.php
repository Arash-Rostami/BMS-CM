<?php

namespace App\Filament\Resources\Master\PermissionResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\PermissionResource;
use Filament\Actions\CreateAction;

class ManagePermissions extends ManageRecords
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
