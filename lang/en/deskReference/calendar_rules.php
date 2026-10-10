<?php

return [
    'tab_label' => 'Guide: Calendar Rules',

    'tips' => [
        'What it is: a date-driven reminder. A rule watches one date on a module\'s records, shows each one on the Dashboard calendar, and sends alerts before and on that date.',
        'Example: payment due soon. Module: Payment, Date column: Payment deadline, Lead times: 7, 3, 1.',
        'Example: follow up a Purchase Request 2 days after approval. Module: Purchase Request, Date column: Approval date, Day shift: +2, no Lead times, Also alert on the day itself: on.',
        'Example: a Proforma Invoice offer is about to expire. Module: Proforma Invoice, Date column: Validity date, Lead times as you like.',
        'Example: goods arriving. Module: Shipment, Date column: ETA (estimated arrival), Lead times: 3, 1.',
        'Timing: a new rule appears within seconds; edits show after about 2 minutes; everything is rebuilt daily at 02:00; alerts go out at 07:00. A queue worker must be running.',
        'Limit: a change that only touches a link between records, or a direct database write, appears at the next 02:00 rebuild.',
    ],

    'terms' => [
        ['term' => 'Module', 'definition' => 'Which records the rule watches, such as Payment or Shipment. The dates and conditions depend on it.'],
        ['term' => 'Date column', 'definition' => 'The date the rule counts from. Each option has a short plain meaning beside it.'],
        ['term' => 'Day shift', 'definition' => 'Moves the date by some days. +2 means two days after it, -1 one day before it.'],
        ['term' => 'Lead times', 'definition' => 'How many days before the date you want an alert, for example 7, 3, 1. Any number from 0 to 120.'],
        ['term' => 'Also alert on the day itself', 'definition' => 'Sends one more alert on the date.'],
        ['term' => 'Heads-up', 'definition' => 'Informational. It leaves "Needs attention" once the date has passed.'],
        ['term' => 'Action', 'definition' => 'Keeps showing, and alerts as overdue, until the date is handled.'],
        ['term' => 'Visibility', 'definition' => 'Who sees the rule and gets its alerts: Only me, Everyone, Specific users or By roles.'],
        ['term' => 'Shared with', 'definition' => 'Appears for Specific users. Only people who can view the module can be picked.'],
        ['term' => 'Roles', 'definition' => 'Appears for By roles. Members who can also view the module see the rule and get its alerts.'],
        ['term' => 'Also notify these emails', 'definition' => 'For people without an account, up to 10 addresses, by email only. They see the names or numbers of the records in the alert.'],
        ['term' => 'Notification channel', 'definition' => 'In-app, Email or Both. Outside emails need Email or Both.'],
        ['term' => 'Conditions tab', 'definition' => 'Narrows which records count, for example only one department or only certain statuses. Leave it empty to include all.'],
        ['term' => 'Show matching records', 'definition' => 'Preview button that tells you how many records match and shows a few examples.'],
    ],

    'process' => [
        ['title' => 'Press Create', 'description' => 'The form opens on the Rule & sharing tab.'],
        ['title' => 'Name, Type and Channel', 'description' => 'Give a short name, pick Heads-up or Action, then the channel.'],
        ['title' => 'Module and Date column', 'description' => 'Choose the module first; the Date column list then fills in, grouped by module.'],
        ['title' => 'Day shift, Lead times, day itself', 'description' => 'Set the shift if the reminder is not on the date itself, add the lead days, and switch on the day-of alert if you want it.'],
        ['title' => 'Visibility', 'description' => 'Choose who sees it. Add users or roles when the choice asks for them, and outside emails if needed.'],
        ['title' => 'Color', 'description' => 'Pick a color so you can tell rules apart on the calendar.'],
        ['title' => 'Conditions tab', 'description' => 'In the Table box pick the module itself, a directly linked table, or one reached through it. Add conditions from the dropdowns. For "this OR that", use the Add an alternative set (OR) button.'],
        ['title' => 'Preview and save', 'description' => 'Press Show matching records to check the count, then save. Use Active to pause the rule later.'],
    ],

    'dos' => [
        'Use one rule per purpose and a clear name such as "Shipment arrival".',
        'Use Action for things someone must do, and Heads-up for things that are only good to know.',
        'Check the preview before saving so you know the rule matches what you expect.',
        'Pick roles when a whole team should see the rule; members need view permission on the module.',
        'Use Duplicate to make a variation of an existing rule quickly.',
        'Use the Activity button on the calendar to see why a record did or did not appear.',
    ],

    'donts' => [
        'Do not expect instant edits; changes to an existing rule show after about 2 minutes.',
        'Do not create near-copies of a rule; if a similar one exists, merge your lead times into it.',
        'Do not add outside emails with the In-app channel; they only receive email.',
        'Do not expect more than one message per rule per person per day; it lists all due items together.',
        'Do not leave a rule you no longer need running; turn it off.',
    ],
];
