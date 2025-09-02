<?php


return [
    'general' => [
        'model_label' => 'Company',
        'plural_model_label' => 'Companies',
        'navigation_group' => 'Master Data',
    ],
    'form' => [
        'name' => 'Name (Persian)',
        'english_name' => 'Name (English)',
        'description' => 'Description',
        'is_active' => 'Is Active?',
        'helper_is_active' => 'Specifies the active/inactive status of this record.',
        'basic_information' => 'General',
        'company_classification' => 'Classification',
        'classification_description' => 'Define the business types and roles of this company',
        'company_types' => 'Company Types',
        'company_types_helper' => 'Select one or more types that describe this company',
        'company_types_description' => 'Choose multiple types to define what kind of business this company operates',
        'creator' => 'Created By',
        'updater' => 'Last Updated By',
        'validation_name' => 'Only Persian characters are allowed.',
        'validation_english_name' => 'Only English letters are allowed.',
        'validation_name_unique' => 'This Persian name is already taken.',
        'validation_english_name_unique' => 'This English name is already taken.',
    ],
    'table' => [
        'name' => 'Name (Persian)',
        'english_name' => 'Name (English)',
        'description' => 'Description',
        'is_active' => 'Status',
        'only_active' => 'Only Active',
        'only_inactive' => 'Only Inactive',
        'company_types' => 'Types',
        'creator' => 'Created By',
        'updater' => 'Last Updated By',
        'created_at' => 'Date Created',
        'updated_at' => 'Last Updated',
        'deleted_at' => 'Date Deleted',
    ],

    'filters' => [
        'company_types' => 'Company Types',
        'select_types' => 'Select Types',
        'all_types' => 'All Types',
        'types_indicator' => 'Types: :types',
        'yes' => 'Yes',
        'no' => 'No',
    ],
];
