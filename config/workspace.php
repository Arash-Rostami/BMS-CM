<?php

use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace record-pinning registry
    |--------------------------------------------------------------------------
    | Each key MUST match the `id` used in the landing-page "available" array
    | (resources/views/components/filament/landing-page/custom-workspace.blade.php).
    |
    |  model    : Eloquent model to search.
    |  route    : named Filament EDIT route the pinned record links to.
    |  title    : columns used to build the visible record label.
    |  subtitle : columns used to build the secondary line (optional).
    |  search   : (optional) restrict searched columns for performance.
    |             Omit to search ACROSS ALL COLUMNS of the table.
    */

    'resources' => [

        'purchaseRequests' => [
            'model'    => PurchaseRequest::class,
            'route'    => 'filament.dashboard.resources.purchase-requests.edit',
            'title'    => ['pr_number'],
            'subtitle' => ['urgency_level', 'required_by_date'],
            // 'search' => ['pr_number', 'notes'],
        ],

        'proformaInvoices' => [
            'model'    => ProformaInvoice::class,
            'route'    => 'filament.dashboard.resources.proforma-invoices.edit',
            'title'    => ['invoice_no'],
            'subtitle' => ['contract_no', 'invoice_date'],
        ],

        'registeredOrders' => [
            'model'    => RegisteredOrder::class,
            'route'    => 'filament.dashboard.resources.registered-orders.edit',
            'title'    => ['ro_number'],
            'subtitle' => ['contract_no', 'order_date'],
        ],

        'bankProfiles' => [
            'model'    => BankProfile::class,
            'route'    => 'filament.dashboard.resources.bank-profiles.edit',
            'title'    => ['bp_number'],
            'subtitle' => ['order_number', 'creation_date'],
        ],

        'purchaseOrders' => [
            'model'    => PurchaseOrder::class,
            'route'    => 'filament.dashboard.resources.purchase-orders.edit',
            'title'    => ['po_number'],
            'subtitle' => ['order_date', 'expected_delivery_date'],
        ],

        'payments' => [
            'model'    => Payment::class,
            'route'    => 'filament.dashboard.resources.payments.edit',
            'title'    => ['payment_no'],
            'subtitle' => ['beneficiary_name', 'payment_date'],
        ],

        'shipments' => [
            'model'    => Shipment::class,
            'route'    => 'filament.dashboard.resources.shipments.edit',
            'title'    => ['shipment_no'],
            'subtitle' => ['bl_number', 'contract_no'],
        ],

        'customs' => [
            'model'    => Custom::class,
            'route'    => 'filament.dashboard.resources.customs.edit',
            'title'    => ['custom_no'],
            'subtitle' => ['declaration_no', 'contract_no'],
        ],

    ],
];
