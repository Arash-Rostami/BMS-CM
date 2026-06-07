<?php

namespace App\Filament\Resources\Operational\PaymentResource\Pages;

use App\Filament\Resources\Operational\PaymentResource\Traits\PreparePaymentFromTargetable;
use App\Filament\Resources\PaymentResource;
use Filament\Actions\CreateAction;
use App\Filament\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    use PreparePaymentFromTargetable;

    protected static string $resource = PaymentResource::class;

    protected static bool $canCreateAnother = false;


    public function afterFill(): void
    {
        self::afterFillFromTargetable();
    }
}
