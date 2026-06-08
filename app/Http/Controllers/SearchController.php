<?php

namespace App\Http\Controllers;

use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private static array $fieldMap = [
        'purchaseRequest' => [
            'model'  => PurchaseRequest::class,
            'label'  => 'Purchase Request',
            'icon'   => 'shopping-cart',
            'color'  => 'indigo',
            'fields' => ['number', 'description', 'required_by_date', 'approval_date', 'status_id', 'user_id'],
        ],
        'proformaInvoice' => [
            'model'  => ProformaInvoice::class,
            'label'  => 'Proforma Invoice',
            'icon'   => 'document-text',
            'color'  => 'cyan',
            'fields' => ['number', 'seller_id', 'currency_id', 'invoice_date', 'validity_date', 'status_id'],
        ],
        'registeredOrder' => [
            'model'  => RegisteredOrder::class,
            'label'  => 'Registered Order',
            'icon'   => 'document-check',
            'color'  => 'indigo',
            'fields' => ['number', 'seller_id', 'currency_id', 'order_date', 'validity_date', 'expected_delivery_date', 'insurance_date', 'status_id'],
        ],
        'bankProfile' => [
            'model'  => BankProfile::class,
            'label'  => 'Bank Profile',
            'icon'   => 'building-office',
            'color'  => 'slate',
            'fields' => ['number', 'bank_id', 'currency_id', 'creation_date', 'allocation_date', 'purchase_date', 'delivery_date', 'status_id'],
        ],
        'purchaseOrder' => [
            'model'  => PurchaseOrder::class,
            'label'  => 'Purchase Order',
            'icon'   => 'shopping-bag',
            'color'  => 'amber',
            'fields' => ['number', 'buyer_id', 'seller_id', 'currency_id', 'order_date', 'validity_date', 'expected_delivery_date', 'incoterms', 'status_id'],
        ],
        'payment' => [
            'model'  => Payment::class,
            'label'  => 'Payment',
            'icon'   => 'banknotes',
            'color'  => 'amber',
            'fields' => ['number', 'currency_id', 'amount', 'payment_date', 'payment_deadline', 'status_id'],
        ],
        'shipment' => [
            'model'  => Shipment::class,
            'label'  => 'Shipment',
            'icon'   => 'truck',
            'color'  => 'indigo',
            'fields' => ['number', 'carrier', 'eta', 'etd', 'warehouse_date', 'exit_date', 'status_id'],
        ],
        'custom' => [
            'model'  => Custom::class,
            'label'  => 'Customs',
            'icon'   => 'clipboard-document-check',
            'color'  => 'slate',
            'fields' => ['number', 'clearance_date', 'doc_submission_date', 'ten_percent_exit_date', 'payment_due_date', 'commitment_payment_date', 'rial_return_date', 'status_id'],
        ],
    ];

    public function spotlight(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        if (strlen($term) < 2) {
            return response()->json(['results' => [], 'breadcrumb' => $this->emptyBreadcrumb()]);
        }

        $results    = [];
        $foundTypes = [];

        foreach (self::$fieldMap as $key => $cfg) {
            $model = $cfg['model'];

            $record = $model::select(array_merge(['id'], $cfg['fields']))
                ->where('id', 'like', "%{$term}%")
                ->orWhere('number', 'like', "%{$term}%")
                ->first();

            if (! $record) continue;

            $foundTypes[] = $key;
            $total        = count($cfg['fields']);
            $filled       = 0;

            foreach ($cfg['fields'] as $field) {
                if (! is_null($record->{$field}) && $record->{$field} !== '') $filled++;
            }

            $results[] = [
                'title'     => $cfg['label'],
                'subtitle'  => $record->number ?? ($cfg['label'] . '-' . $record->id),
                'progress'  => $total > 0 ? (int) round(($filled / $total) * 100) : 0,
                'is_binary' => false,
                'icon'      => $cfg['icon'],
                'color'     => $cfg['color'],
            ];
        }

        return response()->json([
            'results'    => $results,
            'breadcrumb' => $this->buildBreadcrumb($foundTypes),
        ]);
    }

    private function emptyBreadcrumb(): array
    {
        return ['proforma' => 'upcoming', 'order' => 'upcoming', 'logistics' => 'upcoming'];
    }

    private function buildBreadcrumb(array $found): array
    {
        $has = fn(string $k) => in_array($k, $found);

        $proforma  = $has('proformaInvoice') ? 'completed' : ($has('purchaseRequest') ? 'active' : 'upcoming');
        $order     = $has('registeredOrder') ? 'completed' : ($has('bankProfile')     ? 'active' : 'upcoming');
        $logistics = $has('shipment')        ? 'completed' : ($has('custom')          ? 'active' : 'upcoming');

        if ($proforma === 'completed' && $order === 'upcoming') $order = 'active';
        if ($order === 'completed' && $logistics === 'upcoming') $logistics = 'active';

        return compact('proforma', 'order', 'logistics');
    }
}
