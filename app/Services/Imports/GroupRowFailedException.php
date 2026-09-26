<?php

namespace App\Services\Imports;

use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class GroupRowFailedException extends RowImportFailedException
{
    public function __construct(public readonly int $rowOffset, string $message)
    {
        parent::__construct($message);
    }
}
