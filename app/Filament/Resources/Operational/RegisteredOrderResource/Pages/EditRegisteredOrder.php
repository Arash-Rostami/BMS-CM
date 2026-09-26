<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Pages;

use App\Filament\Actions\ResubmitAction;
use App\Filament\Actions\ReturnForRevisionAction;
use App\Filament\Pages\EditRecord;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\HandleStatusMutation;
use App\Filament\Resources\RegisteredOrderResource;
use App\Models\RegisteredOrder;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;

class EditRegisteredOrder extends EditRecord
{
    use HandleStatusMutation;

    protected static string $resource = RegisteredOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RegisteredOrderResource::getStatusWorkflowPipelineAction(),
            ResubmitAction::make(RegisteredOrder::TYPE_REGISTERED_ORDER),
            ReturnForRevisionAction::make(RegisteredOrder::TYPE_REGISTERED_ORDER),
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateStatusData($data, $this->getRecord());
    }
}
