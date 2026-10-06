<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\CorrespondenceResource;
use Filament\Actions\CreateAction;

class ListCorrespondences extends ListRecords
{
    protected static string $resource = CorrespondenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
