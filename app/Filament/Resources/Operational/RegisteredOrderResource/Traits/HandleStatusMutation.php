<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use App\Filament\Resources\RegisteredOrderResource;
use App\Models\Status;
use Illuminate\Database\Eloquent\Model;

trait HandleStatusMutation
{
    protected function mutateStatusData(array $data, ?Model $record = null): array
    {
        $newStatus = Status::find($data['status_id'] ?? null);

        if ($newStatus) {
            RegisteredOrderResource::assertStatusTransitionAllowed($record, 'status_id', $newStatus->id);
        }

        return $data;
    }
}
