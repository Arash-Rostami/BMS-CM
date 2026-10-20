<?php

return [
    'intro' => 'This report follows every bank profile through the foreign-currency queue: waiting for allocation, allocated, purchased and received. It counts only bank profiles that exist and are not deleted. Money is never added across currencies: each currency has its own line, and the euro total is a separate conversion.',
    'sections' => [
        'filters' => [
            'title' => 'Filters',
            'intro' => 'The Filters bar is collapsed by default; open it to see the fields. Every filter narrows the whole page at once: the four cards, both charts, the table, the data issues, their counts and the CSV export. Dates apply to the date chosen in Filter dates by (the Creation Date of the bank profile unless you change it). Leave a field empty to apply no limit. If the From Date is later than the Until Date the two are swapped automatically. The Bank, Company and Currency lists show active records only; Registered Order and Status list every record; Supply Source is a fixed list. The one thing filters do not touch is the euro rate behind the euro totals, which always comes from all purchased profiles.',
            'items' => [
                'date_from' => [
                    'title' => 'From Date',
                    'meaning' => 'Hides bank profiles whose chosen date is before this day.',
                    'how' => 'Compares with the date picked in Filter dates by (Creation Date by default). The chosen day itself is included. Profiles with no value in that date stay visible unless you switch off Include undated.',
                    'change' => 'Pick an earlier day to include older profiles, or clear the field to remove the limit. Dates follow the calendar chosen in the top bar.',
                ],
                'date_until' => [
                    'title' => 'Until Date',
                    'meaning' => 'Hides bank profiles whose chosen date is after this day.',
                    'how' => 'Same date and same rule as From Date. The chosen day itself is included.',
                    'change' => 'Pick a later day to include newer profiles, or clear the field to remove the limit.',
                ],
                'date_basis' => [
                    'title' => 'Filter dates by',
                    'meaning' => 'Which date of the bank profile the From and Until dates are compared with.',
                    'how' => 'Creation Date (default), Allocation Date, Purchase Date or Payment Due Date. It applies to the cards, charts, table and issues alike. The exception is the card Purchased in the selected period: when a date range is set, it always uses the Purchase Date, whatever you chose here, and so do the lines of that card when you click them.',
                    'change' => 'Pick another date; clear the field to return to Creation Date.',
                ],
                'include_undated' => [
                    'title' => 'Include undated',
                    'meaning' => 'Decides what happens to profiles that have no value in the chosen date while a date range is set.',
                    'how' => 'On by default: such profiles stay in the cards, table and issues so nothing disappears silently (for example a purchased profile without a Purchase Date). Off: they are hidden. Without a date range this switch does nothing. The card Purchased in the selected period is the exception: it never counts a profile without a Purchase Date, whatever this switch says.',
                    'change' => 'Switch it off to see only profiles that really have a date inside the range.',
                ],
                'bank_id' => [
                    'title' => 'Bank (Agent)',
                    'meaning' => 'Shows only the profiles handled by one bank.',
                    'how' => 'Matches the Bank (Agent) set on each bank profile. Profiles with no bank are hidden once a bank is chosen.',
                    'change' => 'Choose another bank, or clear the field to see all banks.',
                ],
                'company_id' => [
                    'title' => 'Company',
                    'meaning' => 'Shows only the profiles that belong to one company.',
                    'how' => 'Matches the Company set on each bank profile, not the seller or buyer of the order.',
                    'change' => 'Choose another company, or clear the field.',
                ],
                'registered_order_id' => [
                    'title' => 'Registered Order',
                    'meaning' => 'Shows only the profiles linked to one registered order.',
                    'how' => 'Matches the Registered Order of each bank profile. The list contains every registered order, newest first, not only active ones.',
                    'change' => 'Choose another order, or clear the field.',
                ],
                'currency_id' => [
                    'title' => 'Order Registration Currency',
                    'meaning' => 'Shows only the profiles requested in one currency.',
                    'how' => 'Matches the Order Registration Currency of the profile, never the Purchased Currency, everywhere on the page. So the card Purchased in the selected period can still show other currencies in its lines: those are the currencies actually bought for profiles requested in the chosen currency. To limit by the bought currency use the filter Purchased Currency.',
                    'change' => 'Choose another currency, or clear the field.',
                ],
                'purchased_currency_id' => [
                    'title' => 'Purchased Currency',
                    'meaning' => 'Shows only the profiles whose bought currency is one currency.',
                    'how' => 'Matches the Purchased Currency of the profile, everywhere on the page. A profile not yet purchased has no Purchased Currency and is hidden once this filter is set. It combines with the Order Registration Currency filter (both must match).',
                    'change' => 'Choose another currency, or clear the field.',
                ],
                'supply_source' => [
                    'title' => 'Supply Source',
                    'meaning' => 'Shows only the profiles supplied through one source.',
                    'how' => 'Matches the Supply Source of the profile. The sources are Exchange Center, Export (Third-party), Export (Self), Documentary Bill and Other. Profiles with no source are hidden once one is chosen.',
                    'change' => 'Choose another source, or clear the field.',
                ],
                'status_id' => [
                    'title' => 'Status',
                    'meaning' => 'Shows only the profiles in the chosen statuses. You can choose several.',
                    'how' => 'Matches the current Status of the profile. The list shows the queue stages in their working order, then the remaining statuses.',
                    'change' => 'Add or remove statuses. A card only counts the statuses it is about, so choosing only Purchased makes the card Waiting for allocation show zero.',
                ],
                'stuck_days' => [
                    'title' => 'Status days threshold',
                    'meaning' => 'The number of days in one status after which a profile counts as stuck.',
                    'how' => 'Now :th_stuck days; empty means 30. It sets the red colour of the Days in status column and the Too long in one status issue.',
                    'change' => 'Type a whole number of days between 1 and 365.',
                ],
                'th_due_soon' => [
                    'title' => 'Payment due soon (days)',
                    'meaning' => 'How many days ahead a payment due date counts as due soon.',
                    'how' => 'Now :th_due_soon days; empty means 14. It sets the Payment due soon issue.',
                    'change' => 'Type a whole number of days between 1 and 365.',
                ],
                'show_closed' => [
                    'title' => 'Show finished profiles',
                    'meaning' => 'Adds the finished profiles to the table. Off by default.',
                    'how' => 'Finished means the status is Purchased, Received or Rejected. Off: the table lists only the profiles still in progress, still sorted by days in status, and the Timing and payments card tells how many finished profiles are hidden. On: they are listed too. The cards, the charts and the data issues always count every profile. Clicking a line that includes finished profiles (Purchased this month, and the two payment lines) shows them whatever this switch says, so the line value always equals the rows shown. Ticking a finished status in the Status filter lists those profiles even while this is off, because an explicit choice wins over the default hiding.',
                    'change' => 'Turn it on to review finished profiles, off to focus on the open queue.',
                ],
            ],
        ],
        'cards' => [
            'title' => 'Cards',
            'intro' => 'Each card counts bank profiles by status and adds their amounts per currency. Amounts of different currencies are never added together. The euro total converts each currency separately and adds the results. On the cards Waiting for allocation, Allocated, not yet purchased and Purchased this month, the Profiles line and every currency line are clickable: a click filters the table below to exactly the profiles that line counts (for a currency line, the profiles of that card in that currency, using the same currency the card groups by). A chip Table filtered by appears above the table with a Show all rows button, and clicking the same line again also clears it. The cards themselves never change when you click; they keep counting all profiles that match the filters. The euro total, Excluded (no rate) and the two average-days lines are not clickable, because they are a conversion or an average and not a set of profiles. The CSV export follows the clicked filter. The amount lines of Waiting for allocation and Allocated, not yet purchased are in the requested currency, while Purchased lines are in the purchased currency; a note line on each card says which. On the card Timing and payments, the two payment lines are clickable, but not the two average lines or the count of hidden finished profiles.',
            'items' => [
                'queued' => [
                    'title' => 'Waiting for allocation',
                    'meaning' => 'Profiles that still wait for the bank to allocate foreign currency, and how much they ask for.',
                    'how' => 'Counts profiles whose Status is Ready for Allocation, Awaiting Bank Review or Awaiting Supply. The Profiles line is the count. Each currency line is the sum of Requested Amount, grouped by Order Registration Currency. Total in euro converts every currency line at its latest euro rate and adds the results. A currency with no usable rate is left out of that total and listed on the line Excluded (no rate) with its amount. A rate exists only when at least one profile with status Purchased or Received, with both Requested Amount and Purchased Equivalent above zero, was bought between that currency and the euro (in either direction). The newest Purchase Date wins; profiles without a purchase date come last. No rate is derived through a third currency, and the profile fields Exchange Rate and Final Rate are never used. The same applies if no active currency named Euro exists: then nothing can be converted. A currency whose amount is zero is not listed as excluded.',
                    'change' => 'Use the filters to narrow the profiles. To bring an excluded currency into the euro total, a Purchased or Received profile between that currency and the euro must have its Requested Amount, Purchased Currency and Purchased Equivalent filled in. A new rate is used right after that profile is saved. A profile with no currency appears on a line marked with a dash.',
                ],
                'allocated' => [
                    'title' => 'Allocated, not yet purchased',
                    'meaning' => 'Profiles where the bank has allocated currency but the purchase is not recorded yet, and the amount involved.',
                    'how' => 'Counts profiles whose Status is Allocated. Amounts are the Requested Amount per Order Registration Currency. The euro total and the line Excluded (no rate) work exactly as on the card Waiting for allocation.',
                    'change' => 'Use the filters. A profile leaves this card when its Status changes to anything other than Allocated, normally Purchased.',
                ],
                'purchased_month' => [
                    'title' => 'Purchased in the selected period',
                    'meaning' => 'Foreign currency bought in the selected date range (the current month when no range is set), in the currencies actually purchased.',
                    'how' => 'Counts profiles whose Status is Purchased or Received and whose Purchase Date falls inside the From and Until dates; with a range, the Filter dates by choice is ignored for this card (the Purchase Date is always used), so a profile created in June and purchased in September counts for a September range. When no date range is set the card is called Purchased this month and uses the current month; the month follows the calendar chosen in the top bar (Persian or Gregorian). Currency lines are the sum of Purchased Equivalent grouped by Purchased Currency, not the requested currency, so they can differ from the other cards. The euro total and the line Excluded (no rate) work as on the card Waiting for allocation. A purchased profile without a Purchase Date never appears in this card, with or without a date range and whatever Include undated says (see the issue Purchased without a purchase date).',
                    'change' => 'Set From and Until dates to choose the period, or clear them to get the current month. Switch the calendar in the top bar to change which month is counted. To see a profile here, fill in its Purchase Date.',
                ],
                'timing_payments' => [
                    'title' => 'Timing and payments',
                    'meaning' => 'How long profiles usually take from creation to allocation and from allocation to purchase, which profiles have a payment due soon or already late, and how many finished profiles the table hides.',
                    'how' => 'Creation to allocation is the average number of days between Creation Date and Allocation Date. Allocation to purchase is the average between Allocation Date and Purchase Date. All profiles that match the filters are used whatever their status, but a profile with a missing date is ignored in that average. The result is rounded to one decimal. A dash means no profile has both dates. The two average lines are not clickable. Payment due within :th_due_soon days counts profiles not yet Received or Rejected whose Payment Due Date is from today up to :th_due_soon days ahead (the window is the Payment due-soon window filter). Payment overdue counts the same kind of profiles whose Payment Due Date is before today. These are exactly the profiles of the data issues Payment due soon and Payment overdue, and a Purchased profile is still counted until it is Received. Both payment lines are clickable and show their profiles even when Show finished profiles is off. Finished profiles hidden is not clickable; it counts the profiles with status Purchased, Received or Rejected that the table leaves out while Show finished profiles is off.',
                    'change' => 'Use the filters to compare banks, companies or periods. Missing dates can be completed in the General tab of the profile. Change the Payment due-soon window, enter or correct the Payment Due Date on the profile, or set the status to Received once the payment is settled.',
                ],
            ],
        ],
        'charts' => [
            'title' => 'Charts',
            'items' => [
                'funnel' => [
                    'title' => 'Profiles by stage',
                    'meaning' => 'The share of profiles standing at each stage of the queue.',
                    'how' => 'A doughnut with one slice per stage, counting profiles whose current Status is that stage. The stages and their order come from the status workflow kept in the Status master data. If no order is defined there, the order is Ready for Allocation, Awaiting Bank Review, Awaiting Supply, Allocated, Purchased, Received. In that default order the statuses Under Editing, Rejected and Documentary Bill have no slice, so those profiles are in the table but not in the chart.',
                    'change' => 'Every filter applies. Choosing statuses in the Status filter leaves the other slices at zero.',
                ],
                'by_bank' => [
                    'title' => 'Waiting amount by bank',
                    'meaning' => 'How much requested currency is waiting for allocation at each bank.',
                    'how' => 'Uses the same profiles as the card Waiting for allocation (Ready for Allocation, Awaiting Bank Review, Awaiting Supply). Each bank has one bar per Order Registration Currency, with the summed Requested Amount. Different currencies are not converted, so compare bars of the same colour only. Profiles with no bank are shown under a dash.',
                    'change' => 'Every filter applies. The Status filter can empty this chart if it contains none of the three waiting statuses.',
                ],
            ],
        ],
        'table' => [
            'title' => 'Table',
            'intro' => 'One row per bank profile that matches the filters, leaving out finished profiles unless Show finished profiles is on. The Group button of the table groups the rows by Bank. The default order is Days in status, longest first. You can sort by clicking a column heading and choose 10, 25, 50 or 100 rows per page. Use the column picker to show or hide the columns that allow it.',
            'items' => [
                'bp_number' => [
                    'title' => 'BP Number',
                    'meaning' => 'The number of the bank profile. Hover to see the contract number of its order. It can be copied by clicking it.',
                    'change' => 'This column is always shown.',
                ],
                'registered_order' => [
                    'title' => 'Related To',
                    'meaning' => 'The registered order the profile belongs to.',
                    'change' => 'Shown by default and can be hidden.',
                ],
                'bank' => [
                    'title' => 'Bank (Agent)',
                    'meaning' => 'The bank handling the profile.',
                    'change' => 'Shown by default and can be hidden.',
                ],
                'company' => [
                    'title' => 'Company',
                    'meaning' => 'The company the profile belongs to.',
                    'change' => 'Hidden by default; turn it on with the column picker.',
                ],
                'targetable' => [
                    'title' => 'Product/Category',
                    'meaning' => 'The product or category the profile is linked to. A dash means none.',
                    'change' => 'Hidden by default; turn it on with the column picker.',
                ],
                'status' => [
                    'title' => 'Status',
                    'meaning' => 'The current stage of the profile.',
                    'change' => 'This column is always shown. Change the status in the General tab of the profile.',
                ],
                'days_in_status' => [
                    'title' => 'Days in status',
                    'meaning' => 'How many days the profile has been in its current status. The badge is red above :th_stuck days (the Status days threshold filter; default 30) and grey otherwise. It also appears for Received and Rejected profiles, although those never raise the stuck-in-stage issue.',
                    'how' => 'Days from the last recorded change of the Status until today. If the status change was never recorded (older profiles have no status history yet), the days are counted from the last edit of the profile, and hovering over the badge shows a note saying so. Because of that fallback, saving any field of such a profile restarts its count.',
                    'change' => 'The count restarts when the Status changes. Change the limit with the Status days threshold filter.',
                ],
                'requested_amount' => [
                    'title' => 'Requested Amount',
                    'meaning' => 'The amount asked from the bank, in the currency of the next column.',
                    'change' => 'Shown by default and can be hidden. Edit it in the Currency Remittance Status tab of the profile.',
                ],
                'requested_currency' => [
                    'title' => 'Order Registration Currency',
                    'meaning' => 'The currency of the requested amount.',
                    'change' => 'Shown by default and can be hidden.',
                ],
                'purchased_equivalent' => [
                    'title' => 'Purchased Equivalent',
                    'meaning' => 'The amount actually bought, followed by the name of the Purchased Currency. Hover to see the exact figure with all decimals.',
                    'change' => 'This column is always shown. It is filled in the Currency Remittance Status tab of the profile.',
                ],
                'supply_source' => [
                    'title' => 'Supply Source',
                    'meaning' => 'The source through which the currency is supplied. A dash means none.',
                    'change' => 'Hidden by default; turn it on with the column picker.',
                ],
                'final_rate' => [
                    'title' => 'Final/Effective Rate',
                    'meaning' => 'The final rate of the profile, shown with all decimals. A dash means none.',
                    'how' => 'Only displayed; it is never used for the euro conversion.',
                    'change' => 'Hidden by default; turn it on with the column picker.',
                ],
                'commission_rate' => [
                    'title' => 'Commission Rate (%)',
                    'meaning' => 'The commission percentage of the profile. A dash means none.',
                    'change' => 'Hidden by default; turn it on with the column picker.',
                ],
                'creation_date' => [
                    'title' => 'Creation Date',
                    'meaning' => 'The day the profile was created. The date filters work on this date unless Filter dates by says otherwise.',
                    'change' => 'Hidden by default; turn it on with the column picker. Dates follow the calendar chosen in the top bar.',
                ],
                'allocation_date' => [
                    'title' => 'Allocation Date',
                    'meaning' => 'The day the bank allocated the currency.',
                    'change' => 'Hidden by default; turn it on with the column picker.',
                ],
                'purchase_date' => [
                    'title' => 'Purchase Date',
                    'meaning' => 'The day the currency was bought. It decides whether a profile counts in the card Purchased in the selected period.',
                    'change' => 'Hidden by default; turn it on with the column picker.',
                ],
                'payment_due_date' => [
                    'title' => 'Payment Due Date',
                    'meaning' => 'The day the payment is due. A red badge with a warning icon means the due date has been reached (today counts) and the profile is not yet Received or Rejected.',
                    'change' => 'Shown by default and can be hidden. A profile due today is already red here, while the data issues still list it under Payment due soon; the red badge disappears when the profile is Received or Rejected or its due date is moved.',
                ],
                'toggle' => [
                    'title' => 'Showing and hiding columns',
                    'meaning' => 'Columns that can be hidden are Related To, Bank (Agent), Company, Product/Category, Requested Amount, Order Registration Currency, Supply Source, Final/Effective Rate, Commission Rate, the three dates and Payment Due Date. The columns BP Number, Status, Days in status and Purchased Equivalent are always shown.',
                ],
                'export' => [
                    'title' => 'Export button',
                    'meaning' => 'Prepares a CSV file of every profile that matches the current filters, not only the page you see.',
                    'how' => 'The file is built in the background. When it is ready you get a notification with a Download link. It lists one row per profile with the order number, bank, company, product or category, supply source, status, requested and purchased amounts and currencies, final rate, commission rate, the three dates, payment due date and days in status. It does not follow the columns you hid.',
                    'change' => 'Set the filters first, then press the button.',
                ],
            ],
        ],
        'issues' => [
            'title' => 'Data issues',
            'intro' => 'At the bottom of the page, a collapsed section titled with the number of open data issues lists records with missing or inconsistent data. It is shown only when at least one issue exists. Inside it, the chips Error, Warning and Info show how many issues each severity has; clicking a chip narrows the log to that severity, and Show all removes the narrowing. The log has its own Severity and Check filters too. Issues follow the page filters, so the counts change when you change them. Errors are data that is wrong or makes a number unreliable, warnings are data that is incomplete or late, and info is a note. One profile can appear several times, once for each issue it has, so the count is of issues, not of profiles. Each row shows the current value, what is expected, and who last changed the profile and when; for status issues this comes from the status history, for the others from the latest audit entry, and a dash means nothing was recorded. The Record link opens the profile only if you may edit it. If you may not view bank profiles at all, these checks are not shown to you. Results are kept for about 5 minutes, and saving a bank profile refreshes them at once. Issues never block saving; an issue disappears by itself once the data is corrected. Profiles with no status are still included in the payment and stuck-in-stage checks.',
            'items' => [
                'bank_profile_purchased_without_equivalent' => [
                    'title' => 'Purchased without a purchased equivalent (Error)',
                    'meaning' => 'A profile marked as bought has no bought amount, so it adds nothing to the Purchased this month card and cannot give a euro rate.',
                    'how' => 'Status is Purchased or Received and Purchased Equivalent is empty or zero.',
                    'change' => 'Open the profile, tab Currency Remittance Status, and enter the Purchased Equivalent.',
                ],
                'bank_profile_purchased_without_currency' => [
                    'title' => 'Purchased without a purchased currency (Error)',
                    'meaning' => 'The bought amount has no currency, so it shows on a dash line in Purchased this month and cannot be converted to euro.',
                    'how' => 'Status is Purchased or Received and no Purchased Currency is selected.',
                    'change' => 'Open the profile, tab Currency Remittance Status, and select the Purchased Currency.',
                ],
                'bank_profile_purchased_without_purchase_date' => [
                    'title' => 'Purchased without a purchase date (Warning)',
                    'meaning' => 'The profile never counts in Purchased this month and is ignored in the average days to purchase. When a euro rate is chosen, profiles without a date rank last.',
                    'how' => 'Status is Purchased or Received and Purchase Date is empty.',
                    'change' => 'Open the profile, tab General, and enter the Purchase Date.',
                ],
                'bank_profile_allocated_without_allocation_date' => [
                    'title' => 'Allocated or later without an allocation date (Warning)',
                    'meaning' => 'The profile is left out of both averages in the card Timing and payments.',
                    'how' => 'Status is Allocated, Purchased or Received and Allocation Date is empty.',
                    'change' => 'Open the profile, tab General, and enter the Allocation Date.',
                ],
                'bank_profile_missing_exchange_rate' => [
                    'title' => 'Purchased without an exchange rate (Warning)',
                    'meaning' => 'The rate used for the Rial conversion is missing. It does not change any figure on this page, because the euro totals use the purchased and requested amounts instead.',
                    'how' => 'Status is Purchased or Received and Exchange Rate is empty or zero.',
                    'change' => 'Open the profile, tab Currency Remittance Status, and enter the Exchange Rate.',
                ],
                'bank_profile_requested_amount_zero' => [
                    'title' => 'Requested amount is zero or empty (Error)',
                    'meaning' => 'The profile adds nothing to the cards and charts, and cannot be used for a euro rate.',
                    'how' => 'Requested Amount is empty or zero, whatever the status.',
                    'change' => 'Open the profile, tab Currency Remittance Status, and enter the Requested Amount.',
                ],
                'bank_profile_stuck_in_stage' => [
                    'title' => 'Too long in one status (Warning)',
                    'meaning' => 'The profile has not moved for more days than the Status days threshold.',
                    'how' => 'Days in status, counted as in the table column (from the last status change, or from the last edit when no status history exists), are more than :th_stuck (default 30). Received and Rejected profiles are not checked.',
                    'change' => 'Open the profile, tab General, and move the Status forward when the work has progressed. For a profile without status history, any saved edit restarts the count.',
                ],
                'bank_profile_payment_due_soon' => [
                    'title' => 'Payment due soon (Warning)',
                    'meaning' => 'The payment falls due within the Payment due-soon window and the profile is not settled yet.',
                    'how' => 'Payment Due Date is between today and :th_due_soon days from today (default 14), both included, and the Status is not Received or Rejected.',
                    'change' => 'Open the profile, tab General, and set the Status to Received when settled. Correct the Payment Due Date there if it was wrong.',
                ],
                'bank_profile_payment_overdue' => [
                    'title' => 'Payment overdue (Error)',
                    'meaning' => 'The payment date has passed and the profile is not settled. The Payment Due Date cell in the table also turns red.',
                    'how' => 'Payment Due Date is before today and the Status is not Received or Rejected. Statuses such as Under Editing or Documentary Bill are therefore checked as well.',
                    'change' => 'Open the profile, tab General, and set the Status to Received (or Rejected) once settled. Correct the Payment Due Date there if it was wrong.',
                ],
                'bank_profile_status_invalid_type' => [
                    'title' => 'Status of the wrong kind (Error)',
                    'meaning' => 'The profile carries a status that belongs to another list, so it fits no card or chart bar. The current value shows which kind of status it is.',
                    'how' => 'The selected Status is not one of the bank profile statuses.',
                    'change' => 'Open the profile, tab General, and choose a proper Status.',
                ],
                'bank_profile_status_missing' => [
                    'title' => 'No status (Error)',
                    'meaning' => 'The profile is in no card and no chart bar. It still appears in the table and in the payment and stuck-in-stage checks.',
                    'how' => 'The profile has no Status at all.',
                    'change' => 'Open the profile, tab General, and choose the Status.',
                ],
                'bank_profile_date_order_invalid' => [
                    'title' => 'Dates out of order (Error)',
                    'meaning' => 'The allocation or purchase date comes before the date it should follow, so the average days become wrong or negative.',
                    'how' => 'Allocation Date is before Creation Date, or Purchase Date is before Allocation Date (only when both dates exist). The current value shows the offending pair.',
                    'change' => 'Open the profile, tab General, and correct the dates.',
                ],
                'bank_profile_missing_payment_due_date' => [
                    'title' => 'Open profile without a payment due date (Warning)',
                    'meaning' => 'Without a due date the profile can never show as due soon or overdue.',
                    'how' => 'The Status is not Received or Rejected (a profile with no status counts as open) and Payment Due Date is empty.',
                    'change' => 'Open the profile, tab General, and enter the Payment Due Date.',
                ],
                'bank_profile_supplied_without_bank' => [
                    'title' => 'Allocated or later without a bank (Error)',
                    'meaning' => 'The bank chart and the bank filter cannot place the profile.',
                    'how' => 'Status is Allocated, Purchased or Received and no Bank (Agent) is selected.',
                    'change' => 'Open the profile, tab General, and choose the Bank (Agent).',
                ],
                'bank_profile_no_status_history' => [
                    'title' => 'No status history (Info)',
                    'meaning' => 'No status change was ever recorded for this profile, so its Days in status is estimated from the last edit. The current value shows that edit date.',
                    'how' => 'The profile has a Status but no recorded status change.',
                    'change' => 'It clears the next time the Status of the profile is changed and saved, because that change is recorded.',
                ],
            ],
        ],
    ],
];
