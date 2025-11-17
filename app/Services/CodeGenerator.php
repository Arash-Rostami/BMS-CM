<?php

namespace App\Services;

use App\Models\BankProfile;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class CodeGenerator
{
    protected static array $map = [
        'purchase-requests' => [
            'pr_number' => ['model' => PurchaseRequest::class, 'prefix' => 'PR'],
        ],
        'proforma-invoices' => [
            'invoice_no' => ['model' => ProformaInvoice::class, 'prefix' => 'PI'],
        ],
        'registered-orders' => [
            'ro_number' => ['model' => RegisteredOrder::class, 'prefix' => 'RO'],
            'contract_no' => ['model' => RegisteredOrder::class, 'prefix' => 'CT'],
        ],
        'purchase-orders' => [
            'po_number' => ['model' => PurchaseOrder::class, 'prefix' => 'PO'],
        ],
        'bank-profiles' => [
            'bp_number' => ['model' => BankProfile::class, 'prefix' => 'BP'],
        ],
        'payments' => [
            'payment_no' => ['model' => Payment::class, 'prefix' => 'P'],
        ],
    ];

    public static function generate(string $field): string
    {
        $dateCode = now()->format('ymd');
        $segment = Request::segment(2);
        $modelConfigs = static::$map[$segment] ?? null;

        if (!$modelConfigs) {
            return "ERROR-{$dateCode}-URL";
        }

        $data = $modelConfigs[$field] ?? null;

        if (!$data) {
            return "ERROR-{$dateCode}-FIELD";
        }

        $modelClass = $data['model'];
        $prefix = $data['prefix'];

        if (!class_exists($modelClass)) {
            return "ERROR-{$dateCode}-MODEL";
        }

        $base = "{$prefix}-{$dateCode}";
        $table = (new $modelClass)->getTable();

        $last = DB::table($table)
            ->where($field, 'like', "{$base}%")
            ->orderByDesc($field)
            ->value($field);

        if (!$last) {
            return $base;
        }

        $parts = explode('-', $last);
        $seq = (count($parts) < 3) ? 1 : intval(end($parts)) + 1;

        return "{$base}-{$seq}";
    }
}
