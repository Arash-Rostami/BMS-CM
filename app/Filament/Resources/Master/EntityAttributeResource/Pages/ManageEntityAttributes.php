<?php

namespace App\Filament\Resources\Master\EntityAttributeResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\EntityAttributeResource;

class ManageEntityAttributes extends ManageRecords
{
    protected static string $resource = EntityAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
