<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Models\Status;

trait HandleStatusMutation
{
    protected function mutateStatusData(array $data, ?int $originalStatusId = null): array
    {
        // Set approver info if status changed or is set for first time
        if (! $originalStatusId || $data['status_id'] !== $originalStatusId) {
            $data['approver_id'] = auth()->id();
            $data['approval_date'] = now();
        }

        // Clear rejection reason unless status is 'Declined'
        $newStatus = Status::find($data['status_id'] ?? null);
        if (! $newStatus || $newStatus->english_name !== 'Declined') {
            $data['rejection_reason'] = null;
        }

        return $data;
    }
}
