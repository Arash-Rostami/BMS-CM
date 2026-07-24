<?php

return [
    'welcome' => 'Welcome',
    'reset_cache' => [
        'label' => 'Reset Cache',
        'success' => 'Cache reset successfully.',
    ],
    'actions' => [
        'view_tooltip' => 'View',
        'edit_tooltip' => 'Edit',
        'delete_tooltip' => 'Delete',
        'restore_tooltip' => 'Restore',
        'create' => 'Create',
        'add_record' => '˙⋆✮ Create New',
        'view' => 'View',
        'edit' => 'Update',
        'delete' => 'Delete',
    ],
    'bulk' => [
        'activate' => [
            'label' => 'Activate',
            'notification' => 'Selected items have been activated successfully.',
        ],
        'deactivate' => [
            'label' => 'Deactivate',
            'notification' => 'Selected items have been deactivated successfully.',
        ],
    ],
    'relevant_module' => [
        'form' => [
            'purchase_requests' => 'Purchase Requests',
            'purchase_orders' => 'Purchase Orders',
            'proforma_invoices' => 'Proforma Invoices',
            'registered_orders' => 'Registered Orders',
            'related_to' => 'Related To',
            'purchase_requests_related' => 'Purchase Requests',
            'purchase_orders_related' => 'Purchase Orders',
            'proforma_invoices_related' => 'Proforma Invoices',
            'registered_orders_related' => 'Registered Orders',
        ],
        'table' => [
            'related_to' => 'Related to',
            'purchase_requests' => 'Related Purchase Requests',
            'purchase_orders' => 'Related Purchase Orders',
            'proforma_invoices' => 'Related Proforma Invoices',
            'registered_orders' => 'Related Registered Orders',
        ],
    ],
    'attachments' => [
        'attachments' => 'Attachments',
        'attachment_name' => 'Attachment Name',
        'item_attachments' => 'Item Attachments',
        'has_item_attachments' => 'Has Item Attachments',
        'add_item_attachments' => 'Add Item Attachments',
        'validation' => [
            'attachments_max_files' => 'Maximum 10 files allowed',
            'attachments_type' => 'Invalid file format (images, PDF, Excel)',
            'attachments_size' => 'File size must not exceed 2.5 MB',
            'invalid_filename_chars_hint' => 'Please rename the file using only allowed characters (English, Farsi characters, numbers, spaces, dots, dashes, or underscores) and keep the filename reasonably short.',
            'invalid_filename_chars' => 'Invalid or overly long filename.',
            'file_not_available_hint' => 'The uploaded file is no longer available. Please upload again.',
            'file_not_available' => 'File expired. Please re-upload.',
            'metadata_unreadable_hint' => 'Unable to validate uploaded file. Please try uploading again.',
            'metadata_unreadable' => 'File validation failed.',
            'processing_failed' => 'Unable to process file attachment. Please verify file integrity and try again.',
        ],
        'error_title' => 'Error',
        'error_body' => 'An error occurred while processing attachments.',
        'warning_title' => 'Warning',
    ],
    'export' => [
        'completed' => 'Your export has completed and :successful row(s) exported.',
        'failed' => ' :failed row(s) failed to export.',
    ],
    'metrics' => [
        'mt' => 'Metric Ton',
        'kg' => 'Kilogram',
        'lb' => 'Pound',
        'oz' => 'Ounce',
        'm3' => 'Cubic Meter',
        'ft3' => 'Cubic Foot',
        'l' => 'Liter',
        'gal' => 'Gallon',
        'pcs' => 'Pieces',
        'unit' => 'Unit',
    ],
    'calendar_toggle' => [
        'switch_to_gregorian' => 'Switch to Gregorian',
        'switch_to_jalali' => 'Switch to Jalali',
        'jalali_abbr' => 'SH',
        'gregorian_abbr' => 'AD',
    ],
    'nav_dock' => [
        'switch_to_bottom' => 'Switch to bottom dock',
        'switch_to_side' => 'Switch to side navigation',
    ],
    'topbar_pin' => [
        'pin' => 'Pin topbar (disable auto-hide)',
        'unpin' => 'Unpin topbar (enable auto-hide)',
    ],
    'manage_custom_attributes' => [
        'label' => 'Manage Custom Attributes',
        'modal_heading' => 'Manage Custom Attributes',
        'save' => 'Save',
        'key_label' => 'Key',
        'value_label' => 'Value',
        'add_row' => 'Add row',
    ],
    'extra_attributes' => [
        'key' => 'Key',
        'value' => 'Value',
        'add_action' => 'Add Attribute',
    ],
    'desk_reference' => [
        'tab_label' => 'Desk Reference',
        'listen_prompt' => 'Prefer to listen?',
        'watch_prompt' => 'Prefer to watch?',
        'search_placeholder' => 'Search this reference…',
        'terms_heading' => 'Terminology',
        'process_heading' => 'Process',
        'dos_donts_heading' => "Do's & Don'ts",
        'disclaimer' => 'Operational guidance, reviewed :date. Regulations change — confirm current rules before acting.',
        'action_label' => 'Desk Reference',
        'modal_heading' => 'Desk Reference',
        'tab_text' => 'Reference',
        'tab_media' => 'Media',
        'tips_heading' => 'Key Tips',
    ],
    'greetings' => [
        'morning_saturday' => [
            'Good morning, {name}. A strong start to the week begins today.',
            'Welcome back, {name}. A productive week is ahead.',
        ],
        'afternoon_saturday' => [
            'Good afternoon, {name}. The week is off to a solid start.',
            'Hello {name}, the pace remains steady.',
        ],
        'evening_saturday' => [
            'Good evening, {name}. A productive first day of the week.',
            'Hello {name}, day one is complete.',
        ],
        'night_saturday' => [
            'Good night, {name}. Rest well for a strong tomorrow.',
            'Have a restful night, {name}.',
        ],

        'morning_sunday' => [
            'Good morning, {name}. Consistency is what builds results.',
            'Hello {name}, focus is the key to today.',
        ],
        'afternoon_sunday' => [
            'Good afternoon, {name}. Halfway through the day, on track.',
            'Hello {name}, a short break can sharpen the rest of your day.',
        ],
        'evening_sunday' => [
            'Good evening, {name}. Another productive day closed out.',
            'Hello {name}, steady progress continues to pay off.',
        ],
        'night_sunday' => [
            'Good night, {name}. Rest well and recharge.',
            'Sleep well, {name}. Tomorrow starts fresh.',
        ],

        'morning_monday' => [
            "Good morning, {name}. Today's priorities are worth your full focus.",
            'Hello {name}, staying focused makes the difference.',
        ],
        'afternoon_monday' => [
            'Good afternoon, {name}. Keep the momentum going.',
            'Hello {name}, every completed task moves things forward.',
        ],
        'evening_monday' => [
            'Good evening, {name}. Two productive days behind you.',
            'Hello {name}, solid work today.',
        ],
        'night_monday' => [
            "Good night, {name}. You've earned some rest.",
            'Rest well, {name}. Tomorrow will be even better.',
        ],

        'morning_tuesday' => [
            'Good morning, {name}. This could be your most productive day this week.',
            'Hello {name}, handle the priorities early for a smoother day.',
        ],
        'afternoon_tuesday' => [
            "Good afternoon, {name}. You're through the midweek point.",
            'Hello {name}, take a short break and continue with focus.',
        ],
        'evening_tuesday' => [
            'Good evening, {name}. The demanding part of the week is behind you.',
            'Hello {name}, another solid contribution today.',
        ],
        'night_tuesday' => [
            'Good night, {name}. Midweek is close.',
            'Rest well, {name}. A calm evening ahead.',
        ],

        'morning_wednesday' => [
            "Good morning, {name}. You've passed the halfway mark.",
            'Hello {name}, steady progress continues to serve you well.',
        ],
        'afternoon_wednesday' => [
            'Good afternoon, {name}. Results are taking shape.',
            'Hello {name}, quality work often builds quietly.',
        ],
        'evening_wednesday' => [
            'Good evening, {name}. Three-quarters of the week complete.',
            "Hello {name}, today's effort will show tomorrow.",
        ],
        'night_wednesday' => [
            'Good night, {name}. One working day remains.',
            "Rest well, {name}. You're closing the week out strong.",
        ],

        'morning_thursday' => [
            'Good morning, {name}. Today is for closing out open items.',
            'Hello {name}, a clean finish starts with a clear plan.',
        ],
        'afternoon_thursday' => [
            'Good afternoon, {name}. A strong finish is built with care.',
            "Hello {name}, today's results will carry into the new week.",
        ],
        'evening_thursday' => [
            'Good evening, {name}. The final workday of the week wraps up well.',
            'Hello {name}, a well-earned end to a productive week.',
        ],
        'night_thursday' => [
            "Good night, {name}. The week's work is done.",
            'Rest well, {name}. Enjoy the time ahead.',
        ],

        'morning_friday' => [
            'Good morning, {name}. Take today at an easier pace.',
            'Hello {name}, enjoy the day off.',
        ],
        'afternoon_friday' => [
            'Good afternoon, {name}. Time to recharge for the week ahead.',
            'Hello {name}, make the most of some well-deserved rest.',
        ],
        'evening_friday' => [
            'Good evening, {name}. A quiet end to the week.',
            'Hello {name}, a good moment to reflect on the week.',
        ],
        'night_friday' => [
            "Good night, {name}. The new week hasn't arrived yet, rest easy.",
            'Rest well, {name}. A strong week awaits.',
        ],
    ],
];
