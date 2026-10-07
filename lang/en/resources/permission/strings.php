<?php

return [
    'general' => [
        'model_label' => 'Permission',
        'plural_model_label' => 'Permissions',
    ],
    'form' => [
        'name' => 'Name',
        'roles' => 'Roles',
        'users' => 'Users',
        'helper_name' => 'Use a stable, descriptive identifier, since roles and policies are matched against this exact name.',
        'validation_name_required' => 'Please enter the permission name.',
        'validation_name_max' => 'The permission name may not exceed 255 characters.',
        'validation_name_unique' => 'This permission name already exists.',
        'validation_name_regex' => 'The name must follow the module.action format, using only lowercase English letters, digits, and underscores (e.g. purchase_request.view).',
        'validation_roles_in' => 'Please choose valid roles only.',
        'validation_users_in' => 'Please choose valid users only.',
        'helper_roles' => 'Assign this permission to specific roles.',
        'helper_users' => 'Directly assign this permission to specific users.',
    ],
    'table' => [
        'name' => 'Name',
        'roles_count' => 'Roles',
        'users_count' => 'Users',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'infolist' => [
        'users' => 'Users',
        'name' => 'Name',
        'roles' => 'Roles',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'actions' => [
        'delete_warning' => 'This will remove this access from :roles role(s) and :users user(s).',
    ],
    'filters' => [
        'module' => '🧩 Module',
        'ungranted' => '🚫 Not granted to anyone',
        'ungranted_indicator' => 'Not granted to anyone',
    ],
    'grouping' => [
        'module' => '🧩 Module',
    ],
    'export' => [
        'export_permissions' => 'Export Permissions',
        'id' => 'ID',
        'name' => 'Name',
        'roles_count' => 'Roles Count',
        'users_count' => 'Users Count',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
];
