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
use Illuminate\Support\Facades\DB;

class CodeGenerator
{
    protected static array $map = [
        'pr_number' => ['model' => PurchaseRequest::class, 'prefix' => 'PR'],
        'invoice_no' => ['model' => ProformaInvoice::class, 'prefix' => 'PI'],
        'ro_number' => ['model' => RegisteredOrder::class, 'prefix' => 'RO'],
        'contract_no' => ['model' => RegisteredOrder::class, 'prefix' => 'CT'],
        'po_number' => ['model' => PurchaseOrder::class,   'prefix' => 'PO'],
        'bp_number' => ['model' => BankProfile::class,     'prefix' => 'BP'],
        'payment_no' => ['model' => Payment::class,         'prefix' => 'P'],
        'shipment_no' => ['model' => Shipment::class,        'prefix' => 'S'],
        'custom_no' => ['model' => Custom::class,          'prefix' => 'CU'],
    ];

    public static function generate(string $field): string
    {
        $dateCode = now()->format('ymd');
        $data = static::$map[$field] ?? null;

        if (! $data) {
            return "ERROR-{$dateCode}-FIELD";
        }

        [$modelClass, $prefix] = [$data['model'], $data['prefix']];

        if (! class_exists($modelClass)) {
            return "ERROR-{$dateCode}-MODEL";
        }

        $base = "{$prefix}-{$dateCode}";
        $table = (new $modelClass)->getTable();

        $maxSuffix = DB::table($table)
            ->where($field, 'like', "{$base}%")
            ->lockForUpdate()
            ->pluck($field)
            ->map(fn ($code) => (int) (explode('-', $code)[2] ?? 0))
            ->max();

        return $maxSuffix === null
            ? $base
            : "{$base}-".($maxSuffix + 1);
    }

    public static function fieldsForModel(string $modelClass): array
    {
        return collect(static::$map)
            ->filter(fn ($config) => $config['model'] === $modelClass)
            ->keys()
            ->all();
    }
}
