<?php

namespace App\Filament\Resources\Operational\TargetResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\TargetResource;
use Filament\Actions\CreateAction;

class ManageTargets extends ManageRecords
{
    protected static string $resource = TargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),
        ];
    }
}
