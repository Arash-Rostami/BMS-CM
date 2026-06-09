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
        'validation_name_unique' => 'This permission name already exists.',
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
        'name' => 'Name',
        'roles' => 'Roles',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'filters' => [
        'module' => '🧩 Module',
    ],
    'grouping' => [
        'module' => '🧩 Module',
    ],
];
