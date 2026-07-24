<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Contracts\HasDocumentChecklist;
use App\Services\DocChecklistMatcher;
use Filament\Resources\Pages\EditRecord;

trait SyncsDocumentChecklist
{
    protected function afterCreate(): void
    {
        $this->syncDocumentChecklist();
    }

    protected function afterSave(): void
    {
        $this->syncDocumentChecklist();
    }

    private function syncDocumentChecklist(): void
    {
        if (! $this->record instanceof HasDocumentChecklist) {
            return;
        }

        DocChecklistMatcher::sync($this->record);

        if ($this instanceof EditRecord) {
            $this->fillForm();
        }
    }
}
