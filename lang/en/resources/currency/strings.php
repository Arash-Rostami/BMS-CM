<?php


return [
    'general' => [
        'model_label' => 'Currency',
        'plural_model_label' => 'Currencies',
        'navigation_group' => 'Master Data',
    ],
    'form' => [
        'name' => 'Name (Persian)',
        'english_name' => 'Name (English)',
        'description' => 'Symbol',
        'is_active' => 'Is Active?',
        'helper_is_active' => 'Specifies the active/inactive status of this record.',
        'creator' => 'Created By',
        'updater' => 'Last Updated By',
        'validation_name_required' => 'Please enter the Persian name.',
        'validation_name' => 'Only Persian characters are allowed.',
        'validation_name_unique' => 'The name already exists.',
        'validation_english_name_required' => 'Please enter the English name.',
        'validation_english_name' => 'Only English characters are allowed.',
        'validation_english_name_unique' => 'The English name already exists.'
    ],
    'table' => [
        'name' => 'Name (Persian)',
        'english_name' => 'Name (English)',
        'description' => 'Symbol',
        'is_active' => 'Status',
        'only_active' => 'Only Active',
        'only_inactive' => 'Only Inactive',
        'creator' => 'Created By',
        'updater' => 'Last Updated By',
        'created_at' => 'Date Created',
        'updated_at' => 'Last Updated',
        'deleted_at' => 'Date Deleted',
    ],
];
