<?php

namespace App\Filament\Resources\Operational\PaymentResource\Pages;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\PaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PaymentResource::getStatusWorkflowPipelineAction(),
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        PaymentResource::assertStatusTransitionAllowed($this->getRecord(), 'status_id', $data['status_id'] ?? null);

        return $data;
    }
}
