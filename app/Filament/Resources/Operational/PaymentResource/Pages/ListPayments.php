<?php

namespace App\Filament\Resources\Operational\PaymentResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\PaymentResource;
use Filament\Actions\CreateAction;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([PaymentResource::getDeskReferenceHeaderAction()]),
            CreateAction::make()
                ->icon('heroicon-o-sparkles'),

        ];
    }
}
