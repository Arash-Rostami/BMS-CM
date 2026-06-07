<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ProformaInvoiceResource;
use Filament\Actions;
use App\Filament\Pages\ListRecords;

class ListProformaInvoices extends ListRecords
{
    protected static string $resource = ProformaInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
