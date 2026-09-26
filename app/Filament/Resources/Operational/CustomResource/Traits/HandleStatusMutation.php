<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Models\Status;
use App\Services\StatusWorkflow;
use Illuminate\Database\Eloquent\Model;

trait HandleStatusMutation
{
    protected function assertCustomStatusTransitionsAllowed(array $data, ?Model $record = null): void
    {
        foreach (static::customStatusWorkflowColumns() as $column => $relation) {
            if (! array_key_exists($column, $data)) {
                continue;
            }

            $target = Status::find($data[$column]);

            if (! $target) {
                continue;
            }

            StatusWorkflow::assertAllowed(auth()->user(), $target, $record?->{$relation}, $column);
        }
    }

    protected static function customStatusWorkflowColumns(): array
    {
        return [
            'clearance_status_id' => 'clearanceStatus',
            'bank_guarantee_status_id' => 'bankGuaranteeStatus',
            'commitment_status_id' => 'commitmentStatus',
        ];
    }
}
