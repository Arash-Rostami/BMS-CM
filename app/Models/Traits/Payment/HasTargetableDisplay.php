<?php

namespace App\Models\Traits\Payment;

use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;

trait HasTargetableDisplay
{
    public function getTargetableDisplay(bool $withDate = false): string
    {
        if (! $this->targetable) {
            return '-';
        }

        $name = match ($this->targetable_type) {
            PurchaseOrder::class, RegisteredOrder::class => $withDate
                ? $this->targetable?->formatted_name
                : $this->targetable?->formatted_name_without_date,

            default => $this->targetable?->id,
        };

        return (string) $name;
    }
}
