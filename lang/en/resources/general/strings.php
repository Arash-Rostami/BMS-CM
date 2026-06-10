<?php

return [
    'actions' => [
        'view_tooltip' => 'View',
        'edit_tooltip' => 'Edit',
        'delete_tooltip' => 'Delete',
        'restore_tooltip' => 'Restore',
        'create' => 'Create',
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
    ],
    'calendar_toggle' => [
        'switch_to_gregorian' => 'Switch to Gregorian',
        'switch_to_jalali' => 'Switch to Jalali',
        'jalali_abbr' => 'SH',
        'gregorian_abbr' => 'AD',
    ],
];
