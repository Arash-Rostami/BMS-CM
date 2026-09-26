<?php

namespace App\Jobs;

use Filament\Actions\Imports\Jobs\ImportCsv as BaseImportCsv;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ImportCsv extends BaseImportCsv
{
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("import{$this->import->getKey()}"))
                ->releaseAfter(15)
                ->expireAfter(600),
        ];
    }

    public function handle(): void
    {
        try {
            parent::handle();
        } finally {
            if (method_exists($this->importer, 'resetReservedNumbers')) {
                $this->importer->resetReservedNumbers();
            }
        }
    }
}
