<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\HandleStatusMutation;
use App\Filament\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use Filament\Notifications\Notification;

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

        $data = PurchaseRequestResource::applyInitialStatusOnCreate($data);

        $data = $this->mutateStatusData($data);

        $this->warnIfRecentDuplicateExists($user?->id, $data['cost_center_id'] ?? null);

        return $data;
    }

    protected function warnIfRecentDuplicateExists(?int $requesterId, ?int $costCenterId): void
    {
        if (! $requesterId || ! $costCenterId) {
            return;
        }

        $exists = PurchaseRequest::where('requester_id', $requesterId)
            ->where('cost_center_id', $costCenterId)
            ->where('created_at', '>=', now()->subDay())
            ->whereDoesntHave('status', fn ($query) => $query->where('english_name', 'Declined'))
            ->exists();

        if (! $exists) {
            return;
        }

        Notification::make()
            ->title(__('resources/purchaseRequest/strings.notifications.duplicate_title'))
            ->body(__('resources/purchaseRequest/strings.notifications.duplicate_body'))
            ->warning()
            ->send();
    }
}
