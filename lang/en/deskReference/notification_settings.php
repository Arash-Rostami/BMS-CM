<?php

return [
    'tab_label' => 'Guide: Notification Settings',

    'tips' => [
        'What it is: an event alert. You say "tell me when a record of this table is created, updated or deleted", and the system sends you an alert.',
        'Example: be told whenever a Proforma Invoice is created. Table: Proforma Invoice, Actions: Create. Leave Columns empty.',
        'Example: be told when a Shipment reaches a status. Table: Shipment, Actions: Update, Columns: Status, Column Values: the status you are waiting for.',
        'Example: be told when a Purchase Request becomes urgent. Table: Purchase Request, Actions: Update, Columns: Urgency Level, Column Values: High.',
        'A rule fires only when a watched column changes to one of the chosen values. A record that already has the value does not fire again. A picked date fires when the record moves into that day.',
        'You cannot watch link-table changes, such as attaching a Proforma Invoice to a Purchase Request. Watch the record\'s own columns instead.',
    ],

    'terms' => [
        ['term' => 'Table', 'definition' => 'The kind of record to watch, such as Payment or Shipment. You can pick more than one.'],
        ['term' => 'Actions', 'definition' => 'What happens to the record: Create, Update or Delete.'],
        ['term' => 'Columns', 'definition' => 'Optional. Alert only when these fields change. Leave empty to be told about any change.'],
        ['term' => 'Column Values', 'definition' => 'Optional. Alert only when a watched column changes to one of these values.'],
        ['term' => 'Users', 'definition' => 'Who receives the alert. You are added by default.'],
        ['term' => 'Channel', 'definition' => 'In-App (the Alerts bell), Email, or Both.'],
        ['term' => 'Is Active', 'definition' => 'Turn off to pause the rule without deleting it.'],
        ['term' => 'Who can edit or delete', 'definition' => 'The person who created the rule, or anyone listed as a recipient. Everyone else can only view.'],
        ['term' => 'Language and dates', 'definition' => 'Alert and email text follow the app language. In Farsi, dates appear as Persian dates.'],
    ],

    'process' => [
        ['title' => 'Press Create', 'description' => 'The form opens.'],
        ['title' => 'Select Tables to Monitor', 'description' => 'Pick one or more. The columns and values below update to match.'],
        ['title' => 'Choose Actions', 'description' => 'Create, Update, Delete, or any mix.'],
        ['title' => 'Select Columns to Track', 'description' => 'Optional, used for updates. Pick the fields you care about.'],
        ['title' => 'Select Column Values', 'description' => 'One list per column. Pick from the loaded list, or press + to add a new value.'],
        ['title' => 'Linked records', 'description' => 'For fields that point to another record (a company, a department), pick it by name, in either language.'],
        ['title' => 'Select Users to Notify and the Channel', 'description' => 'Choose the recipients, then In-App, Email or Both.'],
        ['title' => 'Save', 'description' => 'The rule works from the next save of a record. Use Is Active to pause it later.'],
    ],

    'dos' => [
        'Pick columns and values when you only care about one change; it keeps your alerts few and useful.',
        'Choose a single clear event per rule, such as "Shipment status becomes Arrived".',
        'Use the My Notifications filter to find your own rules quickly.',
        'Turn a rule off instead of deleting it when you only need a pause.',
        'Add a short note so you remember why the rule exists.',
    ],

    'donts' => [
        'Do not expect alerts for link-table changes, such as attaching a Proforma Invoice to a Purchase Request.',
        'Do not add people who cannot view the module; they are skipped and receive nothing.',
        'Do not add the same event in many rules; each rule sends its own alert.',
        'Do not expect alerts from bulk imports or direct database changes.',
    ],
];
