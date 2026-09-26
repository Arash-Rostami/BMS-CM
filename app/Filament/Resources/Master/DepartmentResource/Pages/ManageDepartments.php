<?php

namespace App\Filament\Resources\Master\DepartmentResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\DepartmentResource;
use Filament\Actions\CreateAction;

class ManageDepartments extends ManageRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DepartmentResource::getImportAction(),
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
