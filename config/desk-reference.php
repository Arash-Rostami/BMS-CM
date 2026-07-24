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
    'purchaseRequest' => [
        'model' => PurchaseRequest::class,
        'icon' => 'heroicon-o-book-open',
        'group' => 'request_approval',
        'version' => 1,
    ],
    'proformaInvoice' => [
        'model' => ProformaInvoice::class,
        'icon' => 'heroicon-o-book-open',
        'group' => 'request_approval',
        'version' => 1,
    ],
    'registeredOrder' => [
        'model' => RegisteredOrder::class,
        'icon' => 'heroicon-o-book-open',
        'group' => 'order_processing',
        'version' => 1,
    ],
    'bankProfile' => [
        'model' => BankProfile::class,
        'icon' => 'heroicon-o-book-open',
        'group' => 'order_processing',
        'version' => 1,
    ],
    'purchaseOrder' => [
        'model' => PurchaseOrder::class,
        'icon' => 'heroicon-o-book-open',
        'group' => 'procurement_payment',
        'version' => 1,
    ],
    'payment' => [
        'model' => Payment::class,
        'icon' => 'heroicon-o-book-open',
        'group' => 'procurement_payment',
        'version' => 1,
    ],
    'shipment' => [
        'model' => Shipment::class,
        'icon' => 'heroicon-o-book-open',
        'group' => 'logistics',
        'version' => 1,
    ],
    'custom' => [
        'model' => Custom::class,
        'icon' => 'heroicon-o-book-open',
        'group' => 'logistics',
        'version' => 1,
    ],
];
