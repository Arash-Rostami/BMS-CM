<?php

namespace App\Filament\Resources\Operational\CustomResource\Pages;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Traits\HandleStatusMutation;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;

class EditCustom extends EditRecord
{
    use HandleStatusMutation;

    protected static string $resource = CustomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->assertCustomStatusTransitionsAllowed($data, $this->getRecord());

        return $data;
    }
}
