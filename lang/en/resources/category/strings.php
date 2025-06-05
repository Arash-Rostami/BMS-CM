<?php

return [
    'general' => [
        'model_label' => 'Category',
        'plural_model_label' => 'Categories',
        'navigation_group' => 'Master Data',
        'options' => [
            'base_level' => '❶ Base Category',
            'sub_level' => '❷ Sub‑category',
            'line' => '❸ Line',
            'model' => '❹ Model',
        ],
    ],
    'form' => [
        'name' => 'Name (Persian)',
        'english_name' => 'Name (English)',
        'description' => 'Description',
        'parent' => 'Parent Category',
        'level' => 'Level',
        'active' => 'Active',
        'creator' => 'Created By',
        'updater' => 'Last Updated By',
        'validation_name' => 'Only Persian characters are allowed.',
        'validation_name_unique' => 'This Persian name already exists.',
        'validation_english_name' => 'Only English letters and spaces are allowed.',
        'validation_english_name_unique' => 'This English name already exists.',
        'level_helper' => "0 = Base Category ┆ 1 = Sub-category ┆ 2 = Line ┆ 3 = Model ┆ 4+ = Additional levels",
        'validation_level' => 'Level must be a number.',
        'tooltips' => [
            'base_level' => '❶ Base Category',
            'sub_level' => '❷ Sub‑category',
            'line' => '❸ Line',
            'model' => '❹ Model',
        ],
    ],
    'table' => [
        'name' => 'Name (Persian)',
        'english_name' => 'Name (English)',
        'description' => 'Description',
        'parent' => 'Parent Category',
        'level' => 'Level',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'creator' => 'Created By',
        'updater' => 'Last Updated By',
        'created_at' => 'Date Created',
        'updated_at' => 'Last Updated',
        'deleted_at' => 'Date Deleted',
    ],
    'filters' => [
        'level' => 'Category Level',
        'level_placeholder' => 'Select level…',
        'ancestors' => 'Higher-level categories',
        'descendants' => 'Lower-level categories'
    ],
];
