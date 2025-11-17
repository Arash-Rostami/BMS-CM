<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Pages;

use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\HandleStatusMutation;
use App\Filament\Resources\PurchaseRequestResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseRequest extends CreateRecord
{
    use HandleStatusMutation;

    protected static string $resource = PurchaseRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['requester_id'] = $user?->id;

        if ($user && $user->department) {
            $data['department_id'] = $user->department->id;
        }

        $totalCost = collect($data['items'] ?? [])
            ->sum(fn($item) => ((float)($item['quantity'] ?? 0)) * ((float)($item['estimated_cost'] ?? 0)));
        $data['total_estimated_cost'] = number_format($totalCost, 2, '.', '');


        return $this->mutateStatusData($data);
    }

}
