<?php

namespace App\Filament\Resources\Master\NotificationSettingResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\NotificationSettingResource;
use App\Filament\Traits\ShowsAlertsSwitcher;
use Filament\Actions\CreateAction;

class ManageNotificationSettings extends ManageRecords
{
    use ShowsAlertsSwitcher;

    protected static string $resource = NotificationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([NotificationSettingResource::getDeskReferenceHeaderAction()]),
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
                ->mutateDataUsing(fn (array $data): array => static::getResource()::withSanitizedSettings($data)),
        ];
    }
}
