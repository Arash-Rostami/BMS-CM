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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // In rate mode the amount is display-only; keep the DB column NULL so the
        // accessor can fall back to computing it from commission_rate.
        if (($data['commission_input_mode'] ?? 'rate') === 'rate') {
            $data['commission_amount_purchased'] = null;
        }
        unset($data['commission_input_mode']);
        return $data;
    }
}
