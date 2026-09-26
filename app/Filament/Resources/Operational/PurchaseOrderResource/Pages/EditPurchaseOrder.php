<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Pages;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PurchaseOrderResource::getStatusWorkflowPipelineAction(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        PurchaseOrderResource::assertStatusTransitionAllowed($this->getRecord(), 'status_id', $data['status_id'] ?? null);

        return $data;
    }
}
