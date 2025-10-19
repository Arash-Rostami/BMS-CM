<?php

namespace App\Filament\Resources\Operational\TargetResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\TargetResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTargets extends ManageRecords
{
    protected static string $resource = TargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
