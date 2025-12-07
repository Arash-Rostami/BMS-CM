<?php

namespace App\Services;

use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\Cache;

class DashboardStats
{
    public static function get(bool $fresh = false, int $ttlSeconds = 120): array
    {
        $userId = auth()->id() ?? 'guest';
        $cacheKey = "dashboard_counts:{$userId}";

        if ($fresh) {
            return static::compute();
        }

        return Cache::remember($cacheKey, $ttlSeconds, fn() => static::compute());
    }

    protected static function compute(): array
    {
        $map = [
            'payments' => Payment::class,
            'purchase_requests' => PurchaseRequest::class,
            'proforma_invoices' => ProformaInvoice::class,
            'bank_profiles' => BankProfile::class,
            'purchase_orders' => PurchaseOrder::class,
            'registered_orders' => RegisteredOrder::class,
            'shipments' => Shipment::class,
            'customs' => Custom::class,
        ];

        $counts = [];
        foreach ($map as $key => $class) {
            $counts[$key] = (class_exists($class)) ? $class::query()->count() : 0;
        }

        return $counts;
    }
}
