<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Pages;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\CorrespondenceResource;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\HandlesRecipients;
use Filament\Actions;

class EditCorrespondence extends EditRecord
{
    use HandlesRecipients;

    protected static string $resource = CorrespondenceResource::class;

    protected function afterSave(): void
    {
        $this->saveRecipientsToRecord($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($userId = auth()->id()) {
            $this->getRecord()->markReadBy($userId);
        }

        return $this->loadRecipientsToForm($this->getRecord(), $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->parseRecipientsFormData($data);
    }
}
