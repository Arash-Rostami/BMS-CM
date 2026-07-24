<?php

namespace App\Filament\Resources\Master\CategoryResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\CategoryResource;
use Filament\Actions\CreateAction;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
