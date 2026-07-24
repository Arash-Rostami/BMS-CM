<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasDocumentChecklist
{
    public function documentChecklistOptions(): string;

    public function documentChecklist(): array;

    public function setDocumentChecklist(array $rows): void;

    public function isDocumentTrackingEnabled(): bool;

    public function attachments(): MorphMany;
}
