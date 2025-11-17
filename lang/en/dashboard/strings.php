<?php
return [
    'status' => [
        'active' => 'Active',
    ],
    'steps' => [
        'request_approval' => [
            'title' => 'Request & Approval',
            'description' => '❶ Start pre-process: create a purchase request or record a proforma invoice',
            'pending_requests' => 'Purchase requests',
        ],
        'order_processing' => [
            'title' => 'Order Processing',
            'description' => '❷ Start process: register the order and create a bank profile',
            'active_orders' => 'Active orders',
        ],
        'procurement_payment' => [
            'title' => 'Procurement & Payment',
            'description' => '❸ Continue process: create the purchase order and process payments',
            'pending_payments' => 'Pending payments',
        ],
        'logistics' => [
            'title' => 'Logistics & Clearance',
            'description' => '❹ Complete process: create and process shipment, logistics, and customs clearance data',
        ],
    ],
    'view_requests' => 'View requests',
    'view_orders' => 'View orders',
    'proforma' => 'View proforma invoices',
    'banks' => 'View banks',
    'payments' => 'View payments',
    'coming_soon' => 'Coming soon',
    'submodules' => [
        'shipment' => [
            'title' => 'Shipment',
            'description' => 'Track shipments',
        ],
        'logistics' => [
            'title' => 'Logistics',
            'description' => 'Operations management',
        ],
        'custom_clearance' => [
            'title' => 'Customs clearance',
            'description' => 'Handle customs procedures',
        ],
    ],
    'show_submodules' => 'Show submodules',
    'hide_submodules' => 'Hide submodules',
    'flow' => [
        'request' => 'Request',
        'order' => 'Order',
        'payment' => 'Payment',
        'logistics' => 'Logistics',
    ],
    'bms_dashboard' => 'BMS',
    'return_to_main_panel' => 'Return to main dashboard',
];
