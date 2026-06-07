<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Pages;

use App\Filament\Resources\BankProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use App\Filament\Pages\EditRecord;

class EditBankProfile extends EditRecord
{
    protected static string $resource = BankProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
