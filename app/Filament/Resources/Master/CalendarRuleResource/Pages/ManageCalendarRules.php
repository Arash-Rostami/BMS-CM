<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Pages;

use App\Filament\Pages\ManageRecords;
use App\Filament\Resources\CalendarRuleResource;
use App\Filament\Traits\ShowsAlertsSwitcher;
use Filament\Actions\CreateAction;

class ManageCalendarRules extends ManageRecords
{
    use ShowsAlertsSwitcher;

    protected static string $resource = CalendarRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...array_filter([CalendarRuleResource::getDeskReferenceHeaderAction()]),
            CreateAction::make()
                ->icon('heroicon-o-sparkles')
                ->modalWidth('4xl')
                ->mutateFormDataUsing(fn (array $data): array => CalendarRuleResource::assertNotDuplicate($data, null)),
        ];
    }
}
