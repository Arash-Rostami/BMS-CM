<?php

use App\Models\BankProfile;
use App\Models\Correspondence;
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
    | built by App\Livewire\LandingPage\Workspace (resources/views/livewire/landing-page/workspace.blade.php).
    |
    |  model    : Eloquent model to search.
    |  route    : named Filament EDIT route the pinned record links to.
    |  title    : columns used to build the visible record label.
    |  subtitle : columns used to build the secondary line (optional).
    |  status   : (optional) BelongsTo status relation to eager-load; its
    |             localized name is returned per row for the picker status
    |             chip. Omit for models without a single status relation.
    |  translate : (optional) map of subtitle column => lang namespace whose
    |             keys are the lowercase column values (raw enum values get
    |             localized before display; unknown keys fall back to raw).
    |  search   : (optional) restrict searched columns for performance.
    |             Omit to search ACROSS ALL COLUMNS of the table.
    */

    'resources' => [

        'purchaseRequests' => [
            'model' => PurchaseRequest::class,
            'route' => 'filament.dashboard.resources.purchase-requests.edit',
            'title' => ['pr_number'],
            'subtitle' => ['urgency_level', 'required_by_date'],
            'translate' => ['urgency_level' => 'resources/purchaseRequest/strings.general.urgency'],
            'status' => 'status',
            // 'search' => ['pr_number', 'notes'],
        ],

        'proformaInvoices' => [
            'model' => ProformaInvoice::class,
            'route' => 'filament.dashboard.resources.proforma-invoices.edit',
            'title' => ['invoice_no'],
            'subtitle' => ['contract_no', 'invoice_date'],
        ],

        'registeredOrders' => [
            'model' => RegisteredOrder::class,
            'route' => 'filament.dashboard.resources.registered-orders.edit',
            'title' => ['ro_number'],
            'subtitle' => ['contract_no', 'order_date'],
            'status' => 'status',
        ],

        'bankProfiles' => [
            'model' => BankProfile::class,
            'route' => 'filament.dashboard.resources.bank-profiles.edit',
            'title' => ['bp_number'],
            'subtitle' => ['order_number', 'creation_date'],
            'status' => 'status',
        ],

        'purchaseOrders' => [
            'model' => PurchaseOrder::class,
            'route' => 'filament.dashboard.resources.purchase-orders.edit',
            'title' => ['po_number'],
            'subtitle' => ['order_date', 'expected_delivery_date'],
            'status' => 'status',
        ],

        'payments' => [
            'model' => Payment::class,
            'route' => 'filament.dashboard.resources.payments.edit',
            'title' => ['payment_no'],
            'subtitle' => ['beneficiary_name', 'payment_date'],
            'status' => 'status',
        ],

        'shipments' => [
            'model' => Shipment::class,
            'route' => 'filament.dashboard.resources.shipments.edit',
            'title' => ['shipment_no'],
            'subtitle' => ['bl_number', 'contract_no'],
            'status' => 'status',
        ],

        'customs' => [
            'model' => Custom::class,
            'route' => 'filament.dashboard.resources.customs.edit',
            'title' => ['custom_no'],
            'subtitle' => ['declaration_no', 'contract_no'],
        ],

        'correspondence' => [
            'model' => Correspondence::class,
            'route' => 'filament.dashboard.resources.correspondences.edit',
            'title' => ['subject'],
            'subtitle' => ['priority', 'type'],
            'translate' => [
                'priority' => 'resources/correspondence/strings.general.priority',
                'type' => 'resources/correspondence/strings.general.type',
            ],
            'status' => 'status',
        ],

    ],
];
