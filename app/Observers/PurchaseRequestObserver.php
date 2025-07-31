<?php

namespace App\Observers;

use App\Models\PurchaseRequest;
use App\Models\Status;

class PurchaseRequestObserver
{
    /**
     * Handle the PurchaseRequest "created" event.
     */
    public function created(PurchaseRequest $purchaseRequest): void
    {
    }

    /**
     * Handle the PurchaseRequest "updated" event.
     */
    public function updated(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->wasChanged('status_id')) {
            $newStatusName = $purchaseRequest->status->english_name;
            $actionableStatuses = ['Authorized', 'Declined'];

            if (in_array($newStatusName, $actionableStatuses, true)) {
                $itemStatus = Status::findBy('Purchase Item Status', $newStatusName);

                if ($itemStatus) {
                    $purchaseRequest->items()->update([
                        'status_id' => $itemStatus->id,
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Handle the PurchaseRequest "deleted" event.
     */
    public function deleted(PurchaseRequest $purchaseRequest): void
    {
    }

    /**
     * Handle the PurchaseRequest "restored" event.
     */
    public function restored(PurchaseRequest $purchaseRequest): void
    {
    }

    /**
     * Handle the PurchaseRequest "force deleted" event.
     */
    public function forceDeleted(PurchaseRequest $purchaseRequest): void
    {
    }
}
