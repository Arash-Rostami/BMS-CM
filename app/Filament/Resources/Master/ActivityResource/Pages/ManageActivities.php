<?php

namespace App\Filament\Resources\Master\ActivityResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\ActivityResource;

class ManageActivities extends ManageRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
