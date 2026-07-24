<?php

return [
    'tab_label' => 'Desk Reference: Order Registration',
    'scope_label' => 'Order registration and currency allocation stage',
    'covers' => ['registeredOrder', 'bankProfile'],

    'tips' => [
        'Conditional goods may sit in "awaiting permit" for weeks — plan the project timeline around that possibility.',
    ],

    'terms' => [
        ['term' => 'Order Registration (NTSW)', 'definition' => 'Official registration of the order in the comprehensive trade system; prerequisite for currency allocation and clearance.'],
        ['term' => 'HS Code (tariff code)', 'definition' => 'International standard goods classification; basis for rules inquiry and customs duty calculation.'],
        ['term' => 'Currency-use Optimization Permit', 'definition' => 'Ministry-of-Industry cap per HS Code for imports; until it is approved, currency allocation cannot proceed (except for exempt cases).'],
        ['term' => 'Annual Quota', 'definition' => 'Average of the maximum clearances over the past two years per “merchant + tariff code”; seasonal split of 30/30/20/20.'],
        ['term' => 'Commercial Currency Market', 'definition' => 'Official replacement for the NIMA system from 11 Bahman 1403; currency allocation via brokerage of exchanges/agent banks.'],
        ['term' => 'Currency Supply Declaration (SATA code)', 'definition' => 'Output of the currency-allocation stage; readies the file to be sent to customs.'],
        ['term' => 'Conditional Goods', 'definition' => 'Goods requiring a permit (standards, health, veterinary, environment, etc.); the file stays in “awaiting permit” until the issuing authority responds.'],
    ],

    'process' => [
        ['title' => 'Log in to the comprehensive trade system', 'description' => 'As an individual/corporate merchant (requires a valid commercial card).'],
        ['title' => 'Create a new file', 'description' => 'Via “Foreign Trade Operations ← Order Registration File Management”.'],
        ['title' => 'Complete initial data', 'description' => 'Proforma number and date, beneficiary country, proforma validity date.'],
        ['title' => 'Upload documents', 'description' => 'Proforma file and, if needed, the goods’ technical catalog.'],
        ['title' => 'Set the tariff code (HS Code)', 'description' => 'The system automatically runs the “rules inquiry”.'],
        ['title' => 'Await permit (if conditional)', 'description' => 'The file stays on hold until the issuing authority responds (e.g. TTAC for drugs and food).'],
        ['title' => 'Pay the fee and issue the registration number', 'description' => 'After obtaining required permits, or if none are needed.'],
        ['title' => 'Enter currency allocation', 'description' => 'If official currency supply is required.'],
    ],

    'dos' => [
        'Allow 3 to 5 business days for order registration without a special permit.',
        'Design the software so quota percentages and the calculation formula are editable without code changes.',
        'Compute and show three separate values per “merchant + tariff code”: annual quota, current-season quota plus carryover, and consumed/remaining up to season end.',
    ],

    'donts' => [
        'Overlooking the possibility of several weeks’ delay for conditional/sensitive goods.',
        'Mismatches between the goods data and the proforma — a leading cause of registration delay.',
    ],

    'as_of' => 'July 2026',

    'media' => [
        'video' => 'order_processing-fa.mp4',
        'audio' => 'order_processing-fa.m4a',
        'poster' => 'order_processing-fa.jpg',
    ],
];
