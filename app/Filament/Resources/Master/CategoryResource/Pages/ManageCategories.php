<?php

namespace App\Filament\Resources\Master\CategoryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use App\Filament\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
