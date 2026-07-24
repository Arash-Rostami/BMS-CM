<?php

return [
    'tab_label' => 'Desk Reference: Currency & Payment',
    'scope_label' => 'Currency supply, supplier payment and FX-commitment release stage',
    'covers' => ['purchaseOrder', 'payment'],

    'tips' => [
        'Releasing the FX commitment within the legal deadline (3 months, extendable to 6) is mandatory; otherwise commercial-card renewal is suspended.',
    ],

    'terms' => [
        ['term' => 'Iran Currency and Gold Exchange Center', 'definition' => 'Official replacement for the NIMA system from 11 Bahman 1403; its Commercial Currency Market is where currency is supplied and demanded via brokerage of exchanges/agent banks.'],
        ['term' => 'Export Currency Repatriation (FX-commitment)', 'definition' => 'The exporter’s obligation to return export proceeds within the legal deadline via approved channels; this same currency is the main source of import allocation.'],
        ['term' => 'Second FX Hall', 'definition' => 'A separate section for second-group goods (raw materials, intermediate and capital goods) priced by direct agreement between exporter and importer.'],
        ['term' => 'Agent Bank', 'definition' => 'Intermediary between the applicant and the central bank; receives the request, obtains approval, takes the FX-commitment deed and issues the currency supply declaration.'],
        ['term' => 'Currency Supply Declaration (SATA code)', 'definition' => 'Document issued by the agent bank; prerequisite for sending the file to customs.'],
        ['term' => 'No-transfer Currency', 'definition' => 'Currency-supply method via a “no currency transfer” order registration in exchange for reciprocal imports against others’ exports.'],
        ['term' => 'Currency Types', 'definition' => 'Market-discovered rate in the Commercial Currency Market (supply/demand-based), government/preferential (essential goods), and free.'],
    ],

    'process' => [
        ['title' => 'Submit currency-allocation request', 'description' => 'Through the agent bank.'],
        ['title' => 'Central bank approval', 'description' => 'And taking the FX-repatriation/return undertaking from the importer.'],
        ['title' => 'Supply import currency', 'description' => 'Buy from the Commercial Currency Market, or own export proceeds, or a “no currency transfer” order registration.'],
        ['title' => 'Issue Currency Supply Declaration (SATA)', 'description' => 'By the agent bank.'],
        ['title' => 'Transfer funds to the supplier', 'description' => 'T/T, L/C or the method agreed in the proforma.'],
        ['title' => 'Release the FX commitment', 'description' => 'Submit the customs permit and documents to the agent bank within the legal deadline.'],
    ],

    'dos' => [
        'Use the up-to-date name “Currency Exchange Center / Commercial Currency Market” instead of “NIMA system” in internal documents.',
        'Release the FX commitment within the legal deadline (3 months, extendable by up to 6 more months with bank approval).',
        'Show “remaining FX-commitment deadline” and “documents sent to bank” status for each file.',
    ],

    'donts' => [
        'Failing to release the commitment on time — leads to suspension of commercial-card renewal, fines, or central-bank action.',
        'Referencing the “NIMA system” in internal documents — it has effectively been abolished since Bahman 1403.',
    ],

    'as_of' => 'July 2026',

    'media' => [
        'video' => 'procurement_payment-fa.mp4',
        'audio' => 'procurement_payment-fa.m4a',
        'poster' => 'procurement_payment-fa.jpg',
    ],
];
