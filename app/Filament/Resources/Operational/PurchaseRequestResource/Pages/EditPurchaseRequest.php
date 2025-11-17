<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\HandleStatusMutation;
use App\Filament\Resources\PurchaseRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;


class EditPurchaseRequest extends EditRecord
{
    use HandleStatusMutation;

    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $totalCost = collect($data['items'] ?? [])
            ->sum(fn($item) => ((float)($item['quantity'] ?? 0)) * ((float)($item['estimated_cost'] ?? 0)));
        $data['total_estimated_cost'] = number_format($totalCost, 2, '.', '');

        return $this->mutateStatusData($data, $this->getRecord()->status_id);
    }
}
