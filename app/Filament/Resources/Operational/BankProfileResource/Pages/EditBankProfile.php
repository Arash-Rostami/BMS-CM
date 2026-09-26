<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Pages;

use App\Filament\Actions\ManageCustomAttributesAction;
use App\Filament\Pages\EditRecord;
use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\Operational\BankProfileResource\Traits\HandleStatusMutation;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;

class EditBankProfile extends EditRecord
{
    use HandleStatusMutation;

    protected static string $resource = BankProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BankProfileResource::getStatusWorkflowPipelineAction(),
            ViewAction::make(),
            ManageCustomAttributesAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // In rate mode the amount is display-only; write NULL so the DB stays
        // canonical (accessor computes from rate). Switching from amount → rate
        // also clears the previously stored amount.
        if (($data['commission_input_mode'] ?? 'rate') === 'rate') {
            $data['commission_amount_purchased'] = null;
        }
        unset($data['commission_input_mode']);

        return $this->mutateStatusData($data, $this->getRecord());
    }
}
