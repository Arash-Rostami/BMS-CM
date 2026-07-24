<?php

namespace App\Filament\Resources\Operational\CustomResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\CustomResource;
use Filament\Actions\CreateAction;

class ListCustoms extends ListRecords
{
    protected static string $resource = CustomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([CustomResource::getDeskReferenceHeaderAction()]),
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
