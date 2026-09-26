<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\CorrespondenceResource;
use App\Filament\Resources\Operational\CorrespondenceResource\Traits\HandlesRecipients;

class CreateCorrespondence extends CreateRecord
{
    use HandlesRecipients;

    protected static string $resource = CorrespondenceResource::class;

    protected function afterCreate(): void
    {
        $this->saveRecipientsToRecord($this->getRecord());
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->parseRecipientsFormData($data);

        $data['user_id'] = auth()->id();

        $data = CorrespondenceResource::applyInitialStatusOnCreate($data);

        CorrespondenceResource::assertStatusTransitionAllowed(null, 'status_id', $data['status_id'] ?? null);

        return $data;
    }
}
