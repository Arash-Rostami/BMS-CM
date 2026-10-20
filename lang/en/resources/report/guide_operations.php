<?php

return [
    'intro' => 'This report follows every open registered order from registration to customs clearance. It shows where each file stands, how long nothing has happened on it, and what changed recently. An order counts as open until it has at least one shipment, every shipment has a customs file, and every customs file has reached the cleared stage (Completed unless you pick another stage in the Count as finished from filter). The order\'s own Status does not close it. Orders without any shipment are always open. Switch on Show cleared files to list closed orders as well.',
    'sections' => [
        'filters' => [
            'title' => 'Filters',
            'intro' => 'Filters narrow the cards, the chart, the table and the data issues together. The latest-updates feed only follows Activity window, Changed by and Module. Every dropdown lists all active master rows, whether or not an order uses them. If the From Date is later than the Until Date, the two are swapped automatically. The Filters bar is collapsed by default; open it to see the fields. The Activity window, Changed by, Module and the threshold fields sit in a collapsed Advanced section inside it; the other filters stay on top.',
            'items' => [
                'date_from' => [
                    'title' => 'From Date',
                    'meaning' => 'Hides orders whose chosen date (see Filter dates by) is earlier than this day.',
                    'how' => 'Compared with the date picked in Filter dates by: Order Date unless you change it. Never with shipment or customs dates. The feed ignores it. Orders with no value in that date stay visible unless you switch off Include undated.',
                    'change' => 'Pick a day, or clear the field to see all open orders.',
                ],
                'date_until' => [
                    'title' => 'Until Date',
                    'meaning' => 'Hides orders whose chosen date is later than this day.',
                    'how' => 'Same rule as From Date; the chosen day itself is included.',
                    'change' => 'Pick a day, or clear the field.',
                ],
                'date_basis' => [
                    'title' => 'Filter dates by',
                    'meaning' => 'Which date of the order the From and Until dates are compared with.',
                    'how' => 'Order Date (default), Validity Date or the day the order was created in the system. It does not change any other rule.',
                    'change' => 'Pick another date; clear the field to return to Order Date.',
                ],
                'include_undated' => [
                    'title' => 'Include undated',
                    'meaning' => 'Decides what happens to orders that have no value in the chosen date while a date range is set.',
                    'how' => 'On by default: such orders stay in the cards, table and issues so nothing disappears silently. Off: they are hidden, because they cannot be placed in the range. Without a date range this switch does nothing.',
                    'change' => 'Switch it off to see only orders that really have a date inside the range.',
                ],
                'seller_id' => [
                    'title' => 'Seller',
                    'meaning' => 'Shows only orders of one seller.',
                    'how' => 'Matches the Seller of the registered order. The list holds all active companies that can act as a seller.',
                    'change' => 'Choose another seller or clear the field.',
                ],
                'buyer_id' => [
                    'title' => 'Buyer',
                    'meaning' => 'Shows only orders of one buyer.',
                    'how' => 'Matches the Buyer of the registered order. The list holds all active buyer companies.',
                    'change' => 'Choose another buyer or clear the field.',
                ],
                'company_id' => [
                    'title' => 'Company',
                    'meaning' => 'Shows only orders where one company is either the seller or the buyer.',
                    'how' => 'Matches the Seller or the Buyer of the registered order, so it is the wider version of the two filters above. The list holds all active companies.',
                    'change' => 'Choose another company or clear the field.',
                ],
                'category_id' => [
                    'title' => 'Category',
                    'meaning' => 'Shows only orders that contain a product of this category or of any category below it.',
                    'how' => 'Looks at the line items of the order. One matching item is enough; the other items of the order are still shown. The list shows the full path of each active category.',
                    'change' => 'Choose a higher category to include more sub-categories, or clear the field.',
                ],
                'product_id' => [
                    'title' => 'Product',
                    'meaning' => 'Shows only orders that have this product among their line items.',
                    'how' => 'One matching line item is enough; the Main products column still lists all products of the order.',
                    'change' => 'Choose another product or clear the field.',
                ],
                'currency_id' => [
                    'title' => 'Currency',
                    'meaning' => 'Shows only orders placed in this currency.',
                    'how' => 'Matches the Currency of the registered order.',
                    'change' => 'Choose another currency or clear the field.',
                ],
                'registered_order_id' => [
                    'title' => 'Registered Order',
                    'meaning' => 'Shows one single order.',
                    'how' => 'The list shows all orders, newest first, including finished ones; a finished order then gives empty cards and an empty table.',
                    'change' => 'Clear the field to go back to all orders.',
                ],
                'status_id' => [
                    'title' => 'Status',
                    'meaning' => 'Shows only orders whose own Status is one of the chosen ones. You can choose several.',
                    'how' => 'Matches the Status of the registered order (for example Draft or Submitted), not the status of its shipment, customs or bank profile.',
                    'change' => 'Add or remove statuses, or clear the field.',
                ],
                'stage' => [
                    'title' => 'Current stage',
                    'meaning' => 'Shows only orders that are at one pipeline stage: Registered Order, Bank Profile, Payment, Shipment or Customs & Clearance.',
                    'how' => 'Uses the same stage rule as the Open files by stage card, so cards, charts, table and issues all narrow together.',
                    'change' => 'Choose another stage or clear the field.',
                ],
                'show_closed' => [
                    'title' => 'Show cleared files',
                    'meaning' => 'Adds the closed orders to the report.',
                    'how' => 'Off by default: only open orders are listed, and the first card shows how many closed orders are hidden. On: closed orders join the cards, charts, table and issues.',
                    'change' => 'Switch it on to include closed orders.',
                ],
                'cleared_from_stage' => [
                    'title' => 'Count as finished from',
                    'meaning' => 'The customs stage from which a file counts as cleared, and so as closed.',
                    'how' => 'A customs file is cleared when its Clearance Status is this stage or any later one in the stage order. Empty means Completed. An order is closed when every shipment has customs files and all of them are cleared.',
                    'change' => 'Pick an earlier stage to close orders sooner, or clear the field for Completed.',
                ],
                'activity_window' => [
                    'title' => 'Activity window',
                    'meaning' => 'How far back the latest-updates feed looks.',
                    'how' => 'Choices are the last 24 hours, 7 days or 30 days, counted back from now. Default: 7 days. Only the feed uses it; it does not change the idle days.',
                    'change' => 'Pick a longer window to see older changes in the feed.',
                ],
                'causer_id' => [
                    'title' => 'Changed by',
                    'meaning' => 'Shows in the feed only the changes made by one user.',
                    'how' => 'The list holds all users. Only the feed uses it. Changes saved without a user (shown as System) never match a chosen user.',
                    'change' => 'Choose a user, or clear the field to see everybody.',
                ],
                'module' => [
                    'title' => 'Module',
                    'meaning' => 'Shows in the feed only changes of one module.',
                    'how' => 'The list holds the eight modules the feed covers: Purchase Request, Registered Order, Purchase Order, Payment, Shipment, Customs, Bank Profile and Proforma Invoice. A module you have no permission to view stays empty. Only the feed uses it.',
                    'change' => 'Choose another module or clear the field.',
                ],
                'idle_days' => [
                    'title' => 'Idle days threshold',
                    'meaning' => 'The number of days without activity after which a file counts as idle.',
                    'how' => 'A file is idle when its days idle are more than this number (now :th_idle). Empty means 7. It sets the first line of the Idle files card, the orange colour of the Days idle column, the idle chart and the "no activity" data issue.',
                    'change' => 'Type a whole number of days, 1 or more. A higher number lists fewer files as idle.',
                ],
                'th_idle_error' => [
                    'title' => 'Long idle (days)',
                    'meaning' => 'The number of idle days after which a file counts as idle for a long time.',
                    'how' => 'Now :th_idle_error days; empty means 14. It sets the second line of the Idle files card, the red colour of the Days idle column and the "no activity for a long time" data issue.',
                    'change' => 'Type a whole number of days between 1 and 365.',
                ],
                'th_validity' => [
                    'title' => 'Validity warning (days)',
                    'meaning' => 'How many days ahead an order\'s validity counts as expiring soon.',
                    'how' => 'Now :th_validity days; empty means 30. It sets the Expiring line of the Order validity card and the matching data issue.',
                    'change' => 'Type a whole number of days between 1 and 365.',
                ],
                'th_supply' => [
                    'title' => 'Profile deadline (days)',
                    'meaning' => 'How many days after the Order Date an order without a bank profile counts as late.',
                    'how' => 'Now :th_supply days; empty means 30. It sets the "no bank profile after a long time" data issue.',
                    'change' => 'Type a whole number of days between 1 and 365.',
                ],
            ],
        ],
        'cards' => [
            'title' => 'Cards',
            'intro' => 'Every card counts only open orders that match the filters, so all four cards and the table always describe the same set of files. The correspondence card is the only one that counts messages instead of orders. Click a line in the stage, idle or validity cards (the total line included) to filter the table below to exactly those files; a "Table filtered by" chip appears above the table with a "Show all rows" button, and clicking the same line again clears it. The cards, charts, latest-updates feed and data issues do not change when you click, and the CSV export follows the clicked filter. The correspondence card is not clickable because it counts messages, not files.',
            'items' => [
                'stages' => [
                    'title' => 'Open files by stage',
                    'meaning' => 'How many open orders there are, and how far each has got: Registered Order, Bank Profile, Payment, Shipment, or Customs & Clearance.',
                    'how' => 'The first line is the total of listed orders; the stage lines add up to it. A last, non-clickable line, Closed orders hidden, tells how many closed orders match the filters but are left out (it disappears when Show cleared files is on). Each order is counted once, in the furthest stage that has a record: a customs file puts it in Customs & Clearance, otherwise a shipment puts it in Shipment, otherwise a payment in Payment, otherwise a bank profile in Bank Profile, otherwise it stays in Registered Order. A payment counts when it is on the order itself, or on a purchase order that belongs to this order alone. A purchase order shared by several orders does not count. Deleted records are ignored. The stage is the furthest point reached, not the current status.',
                    'change' => 'Use the filters. To move an order forward, create the next record for it (bank profile, payment, shipment, customs file).',
                ],
                'idle' => [
                    'title' => 'Idle files',
                    'meaning' => 'How many open orders have had no activity for a long time.',
                    'how' => 'Two lines. Idle (warning) counts files idle for more than the Idle days threshold (now :th_idle days; default 7). Idle (critical) counts files idle for more than the Long-idle threshold (now :th_idle_error days; default 14). A file over the second limit is in both lines when the first limit is lower. Days idle are explained in the Days idle column.',
                    'change' => 'Change the Idle days threshold or the Long-idle threshold. Any recorded change on a file takes it out of both lines.',
                ],
                'expiring' => [
                    'title' => 'Order validity',
                    'meaning' => 'How many open orders are about to lose validity, and how many already have.',
                    'how' => 'Expiring: Validity Date from today up to :th_validity days ahead (the Validity warning window filter; default 30), both days included, and the order has no purchase order. Already expired: Validity Date before today (today is not expired) and the order has no purchase order; the badge on the Validity Date column uses the same rule. Orders without a Validity Date are in neither of those lines; they are counted on their own line, Without a validity date.',
                    'change' => 'Enter a later Validity Date on the order, or close the file by completing customs on all its shipments.',
                ],
                'correspondence' => [
                    'title' => 'Priority correspondence',
                    'meaning' => 'How many messages of High or Urgent priority are still unanswered on open files.',
                    'how' => 'Counts correspondence with priority High (first line) or Urgent (second line) that is attached to an open order, or to a shipment or customs file of an open order. A message is closed when its Status is Approved or Archived. Draft, Submitted and a message without any status are open. Deleted messages are not counted. Like the data issue of the same name, this card is shown to every viewer of the report, whether or not you may view correspondence.',
                    'change' => 'Change the message\'s Status to Approved or Archived, or lower its priority. Filters on the page narrow it through the order.',
                ],
            ],
        ],
        'charts' => [
            'title' => 'Charts and feed',
            'items' => [
                'stages' => [
                    'title' => 'Open files by stage',
                    'meaning' => 'A doughnut with one slice per stage, from Registered Order to Customs & Clearance.',
                    'how' => 'The slices use the same stage rule and the same filters as the Open files by stage card, so each slice equals the card\'s line for that stage.',
                    'change' => 'Use the filters on the page.',
                ],
                'idle_by_stage' => [
                    'title' => 'Where files are idle',
                    'meaning' => 'A stacked bar per stage splitting its open files into active ones and idle ones, so you can see in which stage files sit without anyone touching them.',
                    'how' => 'It uses the same open files, the same stage rule and the same filters as the Open files by stage chart, and the same last-activity rule as the idle card: a file is idle when more days have passed since its last recorded change (or since its last edit when it has no recorded change) than the Idle days threshold. The two parts of a bar add up to the slice of that stage in the stage chart, and the idle parts add up to the first line of the idle card.',
                    'change' => 'Change Idle days threshold to move the line between active and idle; use the other filters to narrow the files.',
                ],
                'activity_feed' => [
                    'title' => 'Latest updates',
                    'meaning' => 'A list of recent changes, newest first, with when, by whom, which file, which module and what changed.',
                    'how' => 'Each row is one saved edit or one status change. An edit shows the changed fields as old value to new value. A status change shows the field and the old and new status. Only changes inside the Activity window are listed (last 7 days unless changed). The feed covers Purchase Request, Registered Order, Purchase Order, Payment, Shipment, Customs, Bank Profile and Proforma Invoice, and only those modules you are allowed to view; others never appear. The File column shows the registered order number; changes to modules not tied to an order (purchase requests, purchase orders, payments, proforma invoices) show a dash. By shows the user; System means the change was saved with no user recorded. It is not limited to open orders and ignores Seller, Buyer, Category, Product, Currency, Registered Order, Status, the dates and Idle days. Pages of 10, 25 or 50 rows.',
                    'change' => 'Change Activity window, Changed by or Module. To narrow it to one order, look at the File column; the other filters do not reach the feed.',
                ],
            ],
        ],
        'table' => [
            'title' => 'Table',
            'intro' => 'One row per open order that matches the filters. The Group button of the table groups the rows by Seller or by Current stage. By default it is sorted by Days idle, longest first, with 25 rows per page (10, 25, 50 or 100 available). The search box looks in order number, contract number, seller and status. Use the column menu to show or hide columns; Contract Number, Buyer, Currency, Order Date, Operation status, Documentation status, Last updated by and Last correspondence are hidden at first. Search also looks in the product names and codes. Columns computed from the latest records (statuses, Last operation, Last updated by, Days idle colour) cannot be searched or sorted, only Days idle, Last activity, Stage and the plain order columns can be sorted. Status columns come from the latest shipment (highest record number) and the latest customs file of the order.',
            'items' => [
                'ro_number' => [
                    'title' => 'Registered Order Number',
                    'meaning' => 'The number of the order. Hover to see its Order Date.',
                ],
                'contract_no' => [
                    'title' => 'Contract Number',
                    'meaning' => 'The contract number of the order. Hidden by default.',
                ],
                'sellerCompany.name' => [
                    'title' => 'Seller',
                    'meaning' => 'The supplier of the order.',
                ],
                'buyerCompany.name' => [
                    'title' => 'Buyer',
                    'meaning' => 'The buyer of the order. Hidden by default.',
                ],
                'currency.name' => [
                    'title' => 'Currency',
                    'meaning' => 'The currency of the order. Hidden by default.',
                ],
                'products' => [
                    'title' => 'Main products',
                    'meaning' => 'The distinct products in the order\'s line items.',
                    'how' => 'The table shows at most three; the CSV export lists all of them.',
                ],
                'stage' => [
                    'title' => 'Current stage',
                    'meaning' => 'The furthest stage the order has reached.',
                    'how' => 'Same rule as the Open files by stage card. Sorting this column does not follow the order of the stages.',
                ],
                'status.name' => [
                    'title' => 'Status',
                    'meaning' => 'The order\'s own Status. It does not decide whether the order is open.',
                ],
                'order_date' => [
                    'title' => 'Order Date',
                    'meaning' => 'The date of the order. Hidden by default.',
                ],
                'purchase_orders_count' => [
                    'title' => 'Purchase orders',
                    'meaning' => 'How many purchase orders were created from this order.',
                    'how' => 'Deleted purchase orders are not counted. A purchase order is what makes the Expired badge on the Validity Date go away.',
                ],
                'validity_date' => [
                    'title' => 'Validity Date',
                    'meaning' => 'The date until which the order is valid.',
                    'how' => 'The cell shows a red Expired badge instead of the date when the Validity Date is before today (today is not expired) and the order has no purchase order. With a purchase order, the plain date stays. The Already expired line and the data issue use the same rule.',
                    'change' => 'Edit the Validity Date on the order\'s General tab.',
                ],
                'fx_status' => [
                    'title' => 'FX status',
                    'meaning' => 'How far the foreign currency supply has got.',
                    'how' => 'Takes the status of every bank profile of the order and shows the one furthest along in the bank profile status sequence. Empty when the order has no bank profile.',
                ],
                'shipment_status' => [
                    'title' => 'Tracking status',
                    'meaning' => 'The Tracking Status of the latest shipment.',
                    'how' => 'Only the latest shipment is used; earlier shipments of the same order are not shown. Empty when the order has no shipment or no status.',
                ],
                'operation_status' => [
                    'title' => 'Operation status',
                    'meaning' => 'The operation status of the latest shipment. Hidden by default.',
                ],
                'doc_status' => [
                    'title' => 'Documentation status',
                    'meaning' => 'The documentation status of the latest shipment. Hidden by default.',
                ],
                'clearance_status' => [
                    'title' => 'Clearance status',
                    'meaning' => 'The Clearance Status of the latest customs file of the order.',
                    'how' => 'Only the latest customs file is shown, even if the order has several.',
                ],
                'last_operation' => [
                    'title' => 'Last operation',
                    'meaning' => 'What happened at the last recorded activity of the file.',
                    'how' => 'For an edit, the names of up to three changed fields, with the number of further ones (+2). For a status change, the field with old status to new status. It shows a dash when the last activity belongs to a module you are not allowed to view, the same rule as the feed.',
                ],
                'last_activity_at' => [
                    'title' => 'Last activity',
                    'meaning' => 'The date and time of the last recorded activity.',
                    'how' => 'The newest saved edit or status change on the order, or on its bank profiles, shipments or customs files. Payments, correspondence and line items do not count. A dash means nothing was recorded; then Days idle counts from the order\'s last update. Records that have no history yet fall in this case.',
                ],
                'last_by' => [
                    'title' => 'Last updated by',
                    'meaning' => 'The user who made the last recorded activity. Hidden by default.',
                    'how' => 'A dash if no user was recorded.',
                ],
                'idle_days' => [
                    'title' => 'Days idle',
                    'meaning' => 'Days since anything was last recorded on the file.',
                    'how' => 'Today minus the Last activity date. When no activity is recorded, it counts from the order\'s last update, or from its creation if it was never updated; the number then carries a tooltip saying so. Colour: red above :th_idle_error days (Long-idle threshold), orange above :th_idle days (Idle days threshold), grey otherwise.',
                    'change' => 'Record any change on the order, bank profile, shipment or customs file to reset it. Change the two thresholds to move the colour limits.',
                ],
                'last_correspondence' => [
                    'title' => 'Last correspondence',
                    'meaning' => 'The subject of the newest message attached directly to the order, with its To recipients below. Hidden by default.',
                    'how' => 'Messages attached to shipments or customs files are not considered here. Cut at 40 characters.',
                ],
                'export_csv' => [
                    'title' => 'CSV export',
                    'meaning' => 'Downloads the report as a CSV file.',
                    'how' => 'The file is prepared in the background, and a notification with a download link appears when it is ready. It holds all open orders that match the current filters, not only the current page, with every column including hidden ones. Table search and sorting are not applied.',
                    'change' => 'Set the filters first, then click the button.',
                ],
            ],
        ],
        'issues' => [
            'title' => 'Data issues',
            'intro' => 'The accordion at the bottom lists records that look wrong or need action. It appears only when at least one issue exists. Errors are red and warnings amber; this report has no information-level checks. The chips show how many issues of each level exist (hidden when zero); click one to filter the log, "Show all" removes that filter. The log can also be filtered by severity and by check, shows errors first, and has links to the record when you may edit it. It follows the page filters, except Activity window, Changed by and Module. It lists only checks for modules you may view (Registered Order, Shipment); a check for a module you cannot view is neither counted nor listed. The correspondence check is shown to every viewer. Results are refreshed about every 5 minutes, and at once when a record is saved. Issues never block saving. Except where stated, a check looks at the listed orders: open ones, or all of them when Show cleared files is on. "Changed by" and "Changed at" show who last changed the record, or the checked status field.',
            'items' => [
                'registered_order_idle_over_threshold' => [
                    'title' => 'No activity for longer than the idle threshold',
                    'meaning' => 'Warning. An open file has had no recorded activity for more days than the Idle days threshold.',
                    'how' => 'Days idle (see the table column) are more than the Idle days threshold (now :th_idle; default 7). The current value shows the days, with a note when they are counted from the last update because no history exists.',
                    'change' => 'Record progress on the order, bank profile, shipment or customs file, for example update the Status on the order\'s General tab. Or raise the Idle days threshold.',
                ],
                'registered_order_idle_over_14' => [
                    'title' => 'No activity for a long time',
                    'meaning' => 'Error. An open file has had no recorded activity for more days than the Long-idle threshold.',
                    'how' => 'Same calculation as above with the Long-idle threshold (now :th_idle_error days; default 14). A file over this limit also appears under the threshold check when the Idle days threshold is lower.',
                    'change' => 'Record progress on the file, as above, or change the Long-idle threshold.',
                ],
                'registered_order_validity_expiring' => [
                    'title' => 'Order validity ends soon',
                    'meaning' => 'Warning. The order\'s Validity Date falls within the Validity warning window.',
                    'how' => 'Validity Date from today to :th_validity days ahead (default 30), both included, and the order has no purchase order.',
                    'change' => 'If the order was renewed, enter the new Validity Date on the General tab. Or finish the file by completing customs on all its shipments.',
                ],
                'registered_order_validity_expired' => [
                    'title' => 'Order validity has passed',
                    'meaning' => 'Error. The Validity Date is in the past and the file is still open.',
                    'how' => 'Validity Date before today (today is not expired) and the order has no purchase order; the badge on the Validity Date column uses the same rule. Orders without a Validity Date are not flagged.',
                    'change' => 'Enter a later Validity Date on the order\'s General tab, or complete customs on all its shipments.',
                ],
                'registered_order_no_items' => [
                    'title' => 'No line items',
                    'meaning' => 'Error. The order has no line items, so no products are known.',
                    'how' => 'The order has no line item; deleted items do not count.',
                    'change' => 'Add at least one item in the Line Items section of the order\'s General tab.',
                ],
                'registered_order_no_bank_profile_after_n_days' => [
                    'title' => 'No bank profile after a long time',
                    'meaning' => 'Warning. No currency supply has been started for the order.',
                    'how' => 'The order has no bank profile and its Order Date is more than :th_supply days in the past (default 30).',
                    'change' => 'Create a bank profile for the order. Correct the Order Date only if it was entered wrongly.',
                ],
                'registered_order_missing_validity_date' => [
                    'title' => 'No validity date',
                    'meaning' => 'Warning. The order has no Validity Date, so it can never show as expiring or expired.',
                    'how' => 'The Validity Date field of a listed order is empty. The same orders are counted on the Without a validity date line of the Order validity card.',
                    'change' => 'Enter the Validity Date on the order\'s General tab.',
                ],
                'registered_order_missing_party' => [
                    'title' => 'Seller or buyer missing',
                    'meaning' => 'Error. The order has no seller or no buyer.',
                    'how' => 'The Seller or the Buyer of a listed order is empty, or points to a company that was deleted. The current value names the missing party.',
                    'change' => 'Choose the missing company on the order\'s General tab.',
                ],
                'registered_order_missing_currency' => [
                    'title' => 'Currency missing',
                    'meaning' => 'Error. The order has no currency, so its amounts cannot be read correctly.',
                    'how' => 'The Currency of a listed order is empty, or points to a currency that was deleted.',
                    'change' => 'Choose the Currency on the order\'s General tab.',
                ],
                'registered_order_in_customs_without_payment_or_bank_profile' => [
                    'title' => 'Shipped or in customs with no payment and no bank profile',
                    'meaning' => 'Warning. The order has reached shipment or customs, but nothing shows how it was paid or how the currency was supplied.',
                    'how' => 'A listed order has a shipment or a customs file, no bank profile, and no payment (on the order itself, or on a purchase order that belongs to this order alone). Deleted records are ignored.',
                    'change' => 'Record the payment or create the bank profile for the order.',
                ],
                'shipment_missing_tracking_status' => [
                    'title' => 'Shipment without tracking status',
                    'meaning' => 'Warning. A shipment seems to be under way, but its tracking status is empty.',
                    'how' => 'A shipment of an open order has no Tracking Status, while an Exit Date or an ETD is filled in.',
                    'change' => 'Choose the Tracking Status on the shipment, in the Logistics & Details tab.',
                ],
                'correspondence_open_high_priority' => [
                    'title' => 'High-priority correspondence still open',
                    'meaning' => 'Warning. A High or Urgent message on an open file has not been closed.',
                    'how' => 'Priority is High or Urgent and the message Status is not Approved or Archived, on an open order or on its shipments or customs files. The current value shows the priority.',
                    'change' => 'Answer the message and set its Status to Approved or Archived in the Message Details tab, or lower its priority.',
                ],
            ],
        ],
    ],
];
