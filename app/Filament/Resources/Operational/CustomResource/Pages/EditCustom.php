<?php

namespace App\Filament\Resources\Operational\CustomResource\Pages;

use App\Filament\Resources\CustomResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use App\Filament\Pages\EditRecord;

class EditCustom extends EditRecord
{
    protected static string $resource = CustomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
