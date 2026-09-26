<?php

return [
    'codes' => [
        401 => [
            'title' => 'Unauthorized',
            'message' => 'You need to sign in to access this page.',
        ],
        403 => [
            'title' => 'Access Forbidden',
            'message' => "You don't have permission to access this page.",
        ],
        404 => [
            'title' => 'Page Not Found',
            'message' => "The page you're looking for doesn't exist or has been moved.",
        ],
        419 => [
            'title' => 'Session Expired',
            'message' => 'Your session has expired for your security. Please refresh the page and try again.',
        ],
        429 => [
            'title' => 'Too Many Requests',
            'message' => "You've made too many requests in a short time. Please wait a moment and try again.",
        ],
        500 => [
            'title' => 'Server Error',
            'message' => 'Something went wrong on our end. Our team has been notified and is working on it.',
        ],
        503 => [
            'title' => 'Under Maintenance',
            'message' => "We're performing scheduled maintenance. Please check back shortly.",
        ],
    ],
    'error_label' => 'Error',
    'occurred_at' => 'Occurred at',
    'go_to_dashboard' => 'Go to Dashboard',
    'go_back' => 'Go Back',

    // In-app save/action failures shown as a Filament notification (not a full error page) —
    // see App\Services\ExceptionPresenter, which classifies the underlying exception.
    'notifications' => [
        'reference_line' => 'Reference: :ref — please share this with support if the problem continues.',
        'number_too_large_title' => 'Number too large',
        'number_too_large_body' => 'One of the values you entered is larger than this field can store. Please check the amount and try again.',
        'value_too_long_title' => 'Text too long',
        'value_too_long_body' => 'One of the values you entered is longer than this field allows. Please shorten it and try again.',
        'invalid_format_title' => 'Unexpected value',
        'invalid_format_body' => "One of the values doesn't match what this field expects. Please review your entries and try again.",
        'missing_required_title' => 'Missing information',
        'missing_required_body' => "A required piece of information wasn't provided. Please fill in all required fields and try again.",
        'duplicate_title' => 'Already exists',
        'duplicate_body' => 'A record with this same value already exists in the system.',
        'fk_in_use_title' => 'Still in use',
        'fk_in_use_body' => "This record can't be changed or removed because other records still depend on it.",
        'fk_invalid_reference_title' => 'Selection no longer available',
        'fk_invalid_reference_body' => 'One of the items you selected no longer exists. Please refresh the page and choose again.',
        'busy_retry_title' => 'System busy',
        'busy_retry_body' => 'The system was briefly busy handling another request. Please try saving again.',
        'connection_lost_title' => 'Connection interrupted',
        'connection_lost_body' => 'The connection to the database was interrupted. Please try again in a moment.',
        'system_config_title' => 'System issue detected',
        'system_config_body' => 'A system configuration issue was detected. Please contact support and share the reference below.',
        'database_title' => 'Could not save',
        'database_body' => 'The system could not save your changes due to a database error. Please try again, and contact support if it continues.',
        'not_found_title' => 'Record not found',
        'not_found_body' => 'This record no longer exists — it may have been deleted or changed by someone else. Please refresh the page.',
        'forbidden_title' => 'Not permitted',
        'forbidden_body' => "You don't have permission to perform this action.",
        'session_expired_title' => 'Session expired',
        'session_expired_body' => 'Your session has expired. Please refresh the page and try again.',
        'payload_too_large_title' => 'Too much data',
        'payload_too_large_body' => "The file or amount of data you're submitting is too large. Please reduce it and try again.",
        'generic_title' => 'Something went wrong',
        'generic_body' => 'An unexpected error occurred while processing your request. Please try again, and contact support if it continues.',
    ],
];
