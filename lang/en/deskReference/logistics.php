<?php

return [
    'tab_label' => 'Desk Reference: Logistics & Clearance',
    'scope_label' => 'Shipping, customs and clearance stage',
    'covers' => ['shipment', 'custom'],

    'tips' => [
        'Iran\'s customs value is calculated on a CIF basis — declare carriage and insurance to customs even on FOB or EXW deals.',
    ],

    'terms' => [
        ['term' => 'Sea freight', 'definition' => 'Cheapest option for high volume; longer transit. FCL = full container / LCL = less than a container.'],
        ['term' => 'Air freight', 'definition' => 'Fast, higher cost; suited to small-volume, urgent or perishable goods.'],
        ['term' => 'Road freight', 'definition' => 'Suited to neighboring countries (Turkey, Iraq, Central Asian states).'],
        ['term' => 'Rail freight', 'definition' => 'A middle option; routes such as China–Iran via Kazakhstan/Turkmenistan.'],
        ['term' => 'THC', 'definition' => 'Terminal Handling Charge.'],
        ['term' => 'Demurrage/Detention', 'definition' => 'Container late-discharge / late-return fees.'],
        ['term' => 'EXW', 'definition' => 'Delivery at the seller’s factory gate; all carriage responsibility passes to the buyer from there.'],
        ['term' => 'FCA', 'definition' => 'Seller clears the goods for export and hands them to the buyer’s carrier.'],
        ['term' => 'FOB', 'definition' => 'Seller is responsible for delivering the goods on board the vessel at the origin port; the most common term in Iran’s sea imports.'],
        ['term' => 'CFR', 'definition' => 'Like FOB plus paying the carriage to the destination port (no insurance).'],
        ['term' => 'CIF', 'definition' => 'Like CFR plus carriage insurance; the most common term in order-registration proformas since it is the basis for Iran’s customs valuation.'],
        ['term' => 'DAP/DDP', 'definition' => 'Delivery at destination with or without paying import duties; more limited use in Iran’s imports.'],
        ['term' => 'Customs Declaration', 'definition' => 'Official filing based on the bill of lading, final invoice, packing list, certificate of origin and order-registration number.'],
        ['term' => 'Customs Value', 'definition' => 'The CIF value of the goods; basis for calculating import duties.'],
        ['term' => 'Import Duties', 'definition' => 'Customs duty + commercial profit + other statutory levies.'],
        ['term' => 'Green/Yellow/Red channel', 'definition' => 'Customs risk assessment: green (no physical inspection), yellow (document check), red (full physical inspection).'],
        ['term' => 'Customs Permit (green slip)', 'definition' => 'Authorization to release the goods from the customs warehouse after full settlement.'],
        ['term' => 'FX-commitment Release', 'definition' => 'Submitting the customs permit to the agent bank within the legal deadline.'],
    ],

    'process' => [
        ['title' => 'Freight Quotation', 'description' => 'Give the forwarder the shipment specs: weight, volume (CBM), packaging type, origin and destination port/airport.'],
        ['title' => 'Compare forwarder quotes', 'description' => 'Freight rate, transit time, payment terms and ancillary costs (THC, demurrage/detention, insurance).'],
        ['title' => 'Choose the Incoterm', 'description' => 'And record it on the proforma/PO.'],
        ['title' => 'File the customs declaration', 'description' => 'Based on the bill of lading, final invoice, packing list, certificate of origin and order-registration number.'],
        ['title' => 'Determine customs value and final tariff code', 'description' => 'On a CIF basis, by the customs expert.'],
        ['title' => 'Calculate and pay import duties', 'description' => 'Customs duty + commercial profit + other statutory levies.'],
        ['title' => 'Risk-channel assignment', 'description' => 'By the customs risk-management system (green/yellow/red).'],
        ['title' => 'Obtain technical approvals', 'description' => 'Standards, health, etc. at customs, if required.'],
        ['title' => 'Issue the customs permit (green slip)', 'description' => 'After full settlement.'],
        ['title' => 'Physical release of the goods', 'description' => 'From the customs warehouse.'],
        ['title' => 'Release the FX commitment', 'description' => 'Submit the customs permit to the agent bank within the legal deadline.'],
    ],

    'dos' => [
        'Declare carriage and insurance costs to customs even on FOB or EXW deals — Iran’s customs value is calculated on a CIF basis.',
        'Obtain quotes from several forwarders and compare freight rate, transit time and payment terms.',
        'Show “remaining FX-commitment deadline” and “documents sent to bank” status for each file.',
        'Submit the customs permit with quantitative/qualitative specs matching the order registration and FX documents.',
    ],

    'donts' => [
        'Ignoring ancillary costs (THC, demurrage/detention, insurance) when comparing freight rates.',
        'Failing to release the FX commitment on time — suspension of commercial-card renewal, fines, or central-bank action.',
    ],

    'as_of' => 'July 2026',

    'media' => [
        'video' => 'logistics-fa.mp4',
        'audio' => 'logistics-fa.m4a',
        'poster' => 'logistics-fa.jpg',
    ],
];
