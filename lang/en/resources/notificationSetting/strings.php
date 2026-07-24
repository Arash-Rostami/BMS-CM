<?php

return [
    'general' => [
        'model_label' => 'Notification',
        'plural_model_label' => 'Notification',
    ],

    'form' => [
        'tables' => 'Select Tables to Monitor',
        'columns' => 'Select Columns to Track',
        'column_values' => 'Select Column Values',
        'users' => 'Select Users to Notify',
        'notification_type' => 'Channel',
        'is_active' => 'Is Active',
        'notes' => 'Additional Notes',
        'actions' => 'Actions',

        'tables_description' => '⚡ Select one or more resources. The available columns and values will update dynamically based on your choice.',

        'validation_notes_max' => 'Please keep your notes to 500 characters or fewer.',

        'helper_notes' => 'Use this space for quick context, but keep it under 500 characters.',
        'helper_actions' => 'Select which database actions trigger this notification.',
        'helper_columns' => 'Trigger notification only when these specific columns change.',
        'helper_column_values' => 'Trigger notification only when columns change to these specific values.',
        'helper_notification_type' => 'Choose how recipients are notified. "Both" sends an in-app alert and an email.',
    ],

    'channels' => [
        'in_app' => 'In-App 💻',
        'email' => 'Email 📧',
        'all' => 'Both 🔔',
        'unknown' => 'Unknown',
    ],

    'table' => [
        'tables' => 'Table',
        'columns' => 'Columns',
        'column_values' => 'Column Values',
        'users' => 'Users',
        'notification_type' => 'Type',
        'actions' => 'Actions',
        'is_active' => 'Active',
        'created_by' => 'Created By',
        'updated_by' => 'Updated By',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'filters' => [
        'actions' => 'Actions',
        'tables' => 'Tables',
        'notification_type' => 'Channel',
        'is_active' => 'Active Status',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'creator' => 'Created By',
        'updater' => 'Updated By',
    ],

    'infolist' => [
        'tables' => 'Tables',
        'actions' => 'Actions',
        'columns' => 'Columns',
        'column_values' => 'Column Values',
        'users' => 'Users',
        'notification_type' => 'Channel',
        'is_active' => 'Active',
        'notes' => 'Notes',
        'created_by' => 'Created By',
        'updated_by' => 'Updated By',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'action_types' => [
        'create' => '🟢 Create',
        'update' => '🟡 Update',
        'delete' => '🔴 Delete',
    ],
];
