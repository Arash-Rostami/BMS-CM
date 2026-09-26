<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Filament\Resources\PurchaseRequestResource;
use App\Models\Status;
use Illuminate\Database\Eloquent\Model;

trait HandleStatusMutation
{
    protected function mutateStatusData(array $data, ?Model $record = null): array
    {
        $newStatus = Status::find($data['status_id'] ?? null);

        if ($newStatus) {
            PurchaseRequestResource::assertStatusTransitionAllowed($record, 'status_id', $newStatus->id);
        }

        if (! $record || $data['status_id'] !== $record->status_id) {
            $data['approver_id'] = auth()->id();
            $data['approval_date'] = now();
        }

        if (! $newStatus || $newStatus->english_name !== 'Declined') {
            $data['rejection_reason'] = null;
        }

        return $data;
    }
}
