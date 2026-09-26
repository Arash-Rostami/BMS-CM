<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Pages;

use App\Filament\Actions\ResubmitAction;
use App\Filament\Actions\ReturnForRevisionAction;
use App\Filament\Pages\EditRecord;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\HandleStatusMutation;
use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;

class EditPurchaseRequest extends EditRecord
{
    use HandleStatusMutation;

    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PurchaseRequestResource::getStatusWorkflowPipelineAction(),
            ResubmitAction::make(PurchaseRequest::TYPE_PURCHASE_REQUEST),
            ReturnForRevisionAction::make(PurchaseRequest::TYPE_PURCHASE_REQUEST),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateStatusData($data, $this->getRecord());
    }
}
