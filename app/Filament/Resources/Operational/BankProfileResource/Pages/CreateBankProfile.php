<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Pages;

use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\Operational\BankProfileResource\Traits\PrepareBankProfileFromRegisteredOrder;
use App\Filament\Pages\CreateRecord;

class CreateBankProfile extends CreateRecord
{
    use PrepareBankProfileFromRegisteredOrder;

    protected static string $resource = BankProfileResource::class;

    public function afterFill(): void
    {
        if (request()->has('registered_order_id')) {
            self::afterFillFromRegisteredOrder();
        }
    }
}
