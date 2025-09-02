<?php

namespace App\Services;

use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class CodeGenerator
{
    protected static array $map = [
        'purchase-orders' => ['model' => PurchaseOrder::class, 'prefix' => 'PO', 'field' => 'po_number'],
        'proforma-invoices' => ['model' => ProformaInvoice::class, 'prefix' => 'PI', 'field' => 'invoice_no'],
    ];

    public static function generate(): string
    {
        $dateCode = now()->format('ymd');
        $segment = Request::segment(2);
        $data = static::$map[$segment] ?? null;

        if (!$data) {
            return "ERROR-{$dateCode}-URL";
        }

        [$modelClass, $prefix, $field] = [
            $data['model'],
            $data['prefix'],
            $data['field'],
        ];

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
