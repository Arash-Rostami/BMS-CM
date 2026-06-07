<?php

namespace App\Filament\Resources\Master\NotificationSettingResource\Pages;

use App\Filament\Resources\NotificationSettingResource;
use Filament\Actions\CreateAction;
use App\Filament\Pages\ManageRecords;

class ManageNotificationSettings extends ManageRecords
{
    protected static string $resource = NotificationSettingResource::class;
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
        ];
    }
}
