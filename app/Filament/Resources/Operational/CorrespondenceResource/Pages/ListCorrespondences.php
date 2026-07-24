<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\CorrespondenceResource;

class ListCorrespondences extends ListRecords
{
    protected static string $resource = CorrespondenceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
