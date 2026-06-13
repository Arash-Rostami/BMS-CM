<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Pages;

use App\Filament\Resources\RegisteredOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use App\Filament\Pages\EditRecord;

class EditRegisteredOrder extends EditRecord
{
    protected static string $resource = RegisteredOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
