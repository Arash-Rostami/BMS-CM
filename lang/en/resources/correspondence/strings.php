<?php

return [
    'general' => [
        'model_label' => 'CorrespondenceResource',
        'plural_model_label' => 'Correspondences',
        'navigation_group' => 'Operational Data',
        'create_new' => '˙⋆✮ Compose',
    ],
    'enums' => [
        'type' => [
            'report' => 'Report',
            'inquiry' => 'Inquiry',
            'warning' => 'Warning',
            'note' => 'Note',
            'announcement' => 'Announcement',
        ],
        'priority' => [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ],
    ],
    'form' => [
        'section_main' => 'Message Content',
        'section_settings' => 'Settings & Visibility',
        'section_recipients' => 'Audience & Mentions',

        'subject' => 'Subject',
        'body' => 'Message Body',
        'type' => 'Type',
        'priority' => 'Priority',
        'status' => 'Status',
        'is_internal' => 'Internal Note',
        'is_private' => 'Private (Restricted)',
        'parent' => 'Reply To',

        'recipients' => 'Recipients',
        'recipients_to' => 'To (Action Required)',
        'recipients_cc' => 'CC (Information Only)',

        'helper_internal' => 'Visible only to internal staff members.',
        'helper_private' => 'Visible ONLY to you and the selected recipients.',

        'validation_required' => 'The :attribute field is required.',
        'validation_unique' => 'This value already exists.',
    ],
    'table' => [
        'subject' => 'Subject',
        'type' => 'Type',
        'priority' => 'Priority',
        'status' => 'Status',
        'visibility' => 'Visibility',
        'recipients' => 'Recipients',
        'creator' => 'Creator',
        'updater' => 'Updater',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'thread' => 'Conversation Thread',
        'thread_prefix' => 'Thread: ',
    ],
    'filters' => [
        'type' => 'Type',
        'priority' => 'Priority',
        'status' => 'Status',
        'creator' => 'Author',
        'my_mentions' => 'My Mentions (Inbox)',
        'created_from' => 'Created From',
        'created_until' => 'Created Until',
    ],
    'infolist' => [
        'tab_general' => 'Message Details',
        'attachments' => 'Attachments',
        'creator' => 'Created By',
    ],
    'export' => [
        'filename' => 'correspondence-export',
    ]
];
