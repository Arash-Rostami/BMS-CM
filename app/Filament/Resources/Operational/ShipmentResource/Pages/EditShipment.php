<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Pages;

use App\Filament\Resources\Operational\ShipmentResource\Traits\HandlesDocumentChecklistForm;
use App\Filament\Resources\Operational\ShipmentResource\Traits\SyncsDocumentChecklist;
use App\Filament\Resources\ShipmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use App\Filament\Pages\EditRecord;

class EditShipment extends EditRecord
{
    use HandlesDocumentChecklistForm, SyncsDocumentChecklist;

    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
