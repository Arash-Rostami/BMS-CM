<?php

namespace App\Filament\Resources\Operational\CustomResource\Pages;

use App\Filament\Resources\CustomResource;
use Filament\Actions\CreateAction;
use App\Filament\Pages\ListRecords;

class ListCustoms extends ListRecords
{
    protected static string $resource = CustomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
