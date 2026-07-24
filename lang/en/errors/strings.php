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
];
