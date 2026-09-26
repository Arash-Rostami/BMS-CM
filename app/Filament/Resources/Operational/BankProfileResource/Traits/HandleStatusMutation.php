<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use App\Filament\Resources\BankProfileResource;
use App\Models\Status;
use Illuminate\Database\Eloquent\Model;

trait HandleStatusMutation
{
    protected function mutateStatusData(array $data, ?Model $record = null): array
    {
        if ($newStatus = Status::find($data['status_id'] ?? null)) {
            BankProfileResource::assertStatusTransitionAllowed($record, 'status_id', $newStatus->id);
        }

        return $data;
    }
}
