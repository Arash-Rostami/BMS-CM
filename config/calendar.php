<?php

return [
    'channels' => ['database'],

    'queue' => env('CALENDAR_QUEUE', 'default'),
    'alert_time' => '07:00',
    'rebuild_time' => '02:00',
    'max_overdue_alerts' => 3,
    'max_path_depth' => 6,
    'preview_limit' => 5,
    'sync_chunk' => 500,
];
