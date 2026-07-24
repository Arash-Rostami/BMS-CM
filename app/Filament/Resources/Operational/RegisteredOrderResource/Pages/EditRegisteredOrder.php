<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Pages;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\RegisteredOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;

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
