<?php

return [
    'tab_label' => 'Desk Reference: Purchase Request',
    'scope_label' => 'Request & Approval stage: Inquiry → PI → PO',
    'covers' => ['purchaseRequest', 'proformaInvoice'],

    'tips' => [
        'An unusually low price can signal low quality or fraud — always compare it against the market average.',
    ],

    'terms' => [
        ['term' => 'Inquiry', 'definition' => 'Initial, informal request to a supplier to find out whether the required goods can be supplied at all; usually without a firm price commitment.'],
        ['term' => 'RFQ (Request for Quotation)', 'definition' => 'Formal request for a precise price based on defined technical specs, quantity and delivery terms.'],
        ['term' => 'Quotation', 'definition' => 'Supplier’s reply to an RFQ; includes unit price, proposed payment terms and quote validity, but is not yet a formal commercial document.'],
        ['term' => 'Proforma Invoice (PI)', 'definition' => 'Final, formal document issued after both parties agree; the legal basis for order registration and currency allocation.'],
        ['term' => 'PO (Purchase Order)', 'definition' => 'Official purchase order the buyer issues against an approved proforma so production/shipment can begin.'],
        ['term' => 'MOQ (Minimum Order Quantity)', 'definition' => 'Minimum acceptable quantity per order from the supplier.'],
        ['term' => 'Lead Time', 'definition' => 'Time from order confirmation until goods are ready for loading.'],
        ['term' => 'L/C (Letter of Credit)', 'definition' => 'Documentary credit; a bank-guaranteed payment method.'],
        ['term' => 'T/T (Telegraphic Transfer)', 'definition' => 'Direct bank wire transfer.'],
        ['term' => 'Sample / Pre-shipment Sample', 'definition' => 'Prototype or pre-shipment sample for quality approval.'],
    ],

    'process' => [
        ['title' => 'Register internal purchase request', 'description' => 'Need, technical specs and approximate budget.'],
        ['title' => 'Internal approval', 'description' => 'By the manager / relevant unit.'],
        ['title' => 'Identify potential suppliers', 'description' => 'Internal database, trade fairs, B2B platforms such as Alibaba.'],
        ['title' => 'Send Inquiry', 'description' => 'To several suppliers simultaneously.'],
        ['title' => 'Receive Quotations and compare', 'description' => 'Price, payment terms and lead time.'],
        ['title' => 'Negotiate price and terms', 'description' => 'Reach final agreement.'],
        ['title' => 'Request and review sample', 'description' => 'If required.'],
        ['title' => 'Obtain final proforma', 'description' => 'From the selected supplier.'],
        ['title' => 'Approve proforma and hand off', 'description' => 'Transfer the file to order registration.'],
    ],

    'dos' => [
        'Verify the company’s registration record in the origin country (year established, operating license).',
        'Query international inspection firms (SGS, Bureau Veritas and similar) for a Factory Audit.',
        'Check quality certificates relevant to the goods (ISO, CE, etc.).',
        'Request a sample and assess quality before placing a high-volume order.',
        'Gauge the supplier’s production capacity against the order volume (prevents delivery delays).',
        'Compare the offered price against the market average (an unusually low price can signal low quality or risk).',
    ],

    'donts' => [
        'Accepting an unusual prepayment — a request for an out-of-the-ordinary advance payment is a red flag.',
        'Ignoring banking/sanctions restrictions on transferring funds to a given country or company, given Iran’s specific currency-transfer conditions.',
    ],

    'as_of' => 'July 2026',

    'media' => [
        'video' => 'request_approval-fa.mp4',
        'audio' => 'request_approval-fa.m4a',
        'poster' => 'request_approval-fa.jpg',
    ],
];
