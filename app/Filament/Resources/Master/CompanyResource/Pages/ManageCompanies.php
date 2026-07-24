<?php

namespace App\Filament\Resources\Master\CompanyResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\CompanyResource;
use Filament\Actions\CreateAction;

class ManageCompanies extends ManageRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
