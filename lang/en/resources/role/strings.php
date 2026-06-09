<?php

return [
    'general' => [
        'model_label' => 'Role',
        'plural_model_label' => 'Roles',
    ],
    'form' => [
        'name' => 'Name',
        'grade' => 'Level',
        'permissions' => 'Permissions',
        'select_all' => 'Select All Permissions (Super Admin)',
        'modules' => 'Grant Full Access by Module',
        'helper_name' => 'Use only English letters and underscores; the name is converted to snake_case automatically.',
        'validation_name_required' => 'Please enter a name for this role.',
        'validation_name_unique' => 'A role with this name already exists.',
        'validation_name_regex' => 'The name may contain only English letters and underscores, with no spaces or numbers.',
        'validation_grade_required' => 'Please select a level for this role.',
        'validation_name_max' => 'The role name may not be longer than 255 characters.',
            'helper_grade' => 'Select the seniority level to prefix the role automatically.',
            'helper_modules' => 'Select modules to quickly assign bulk permissions.',
    ],
    'table' => [
        'name' => 'Name',
        'permissions_count' => 'Permissions Count',
        'users_count' => 'Users Count',
        'created_at' => 'Date Created',
        'updated_at' => 'Last Updated',
    ],
    'infolist' => [
        'name' => 'Name',
        'grade' => 'Level',
        'permissions' => 'Permissions',
        'created_at' => 'Date Created',
        'updated_at' => 'Last Updated',
    ],
    'filters' => [
        'name' => 'Name',
        'grade' => 'Level',
    ],
    'grouping' => [
        'base_name' => 'Name',
        'grade' => 'Level',
    ],
];
