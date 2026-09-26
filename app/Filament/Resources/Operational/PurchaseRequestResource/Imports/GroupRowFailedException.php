<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Imports;

use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class GroupRowFailedException extends RowImportFailedException
{
    public function __construct(public readonly int $rowOffset, string $message)
    {
        parent::__construct($message);
    }
}
