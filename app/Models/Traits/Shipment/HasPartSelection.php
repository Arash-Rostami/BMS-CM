<?php

namespace App\Models\Traits\Shipment;

use Illuminate\Support\Facades\Cache;

trait HasPartSelection
{
    public static function getPartData(int $registeredOrderId, string $contractNo, ?int $excludeId = null): array
    {
        $cacheKey = "shipment_parts_{$registeredOrderId}_{$contractNo}";

        $usedParts = Cache::remember($cacheKey, 300, function () use ($registeredOrderId, $contractNo) {
            return static::query()
                ->where('registered_order_id', $registeredOrderId)
                ->where('contract_no', $contractNo)
                ->pluck('part')
                ->toArray();
        });

        if ($excludeId) {
            $usedParts = array_diff($usedParts, [
                static::find($excludeId)?->part,
            ]);
        }

        $available = array_diff(range(1, 50), $usedParts);

        return [
            'used' => $usedParts,
            'available' => $available,
            'next' => ! empty($available) ? min($available) : null,
        ];
    }

    protected static function bootHasPartSelection(): void
    {
        static::saved(function ($model) {
            Cache::forget("shipment_parts_{$model->registered_order_id}_{$model->contract_no}");
        });

        static::deleted(function ($model) {
            Cache::forget("shipment_parts_{$model->registered_order_id}_{$model->contract_no}");
        });
    }
}
