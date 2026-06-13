<?php

return [
    'general' => [
        'model_label' => 'Permission',
        'plural_model_label' => 'Permissions',
    ],
    'form' => [
        'name' => 'Nom',
        'roles' => 'Rôles',
        'users' => 'Utilisateurs',
        'helper_name' => 'Utilisez un identifiant stable et descriptif, car les rôles et les politiques sont comparés à ce nom exact.',
        'validation_name_required' => 'Veuillez saisir le nom de l\'autorisation.',
        'validation_name_max' => 'Le nom de l\'autorisation ne peut pas dépasser 255 caractères.',
        'validation_name_unique' => 'Ce nom d\'autorisation existe déjà.',
        'helper_roles' => 'Attribuez cette autorisation à des rôles spécifiques.',
        'helper_users' => 'Attribuez directement cette autorisation à des utilisateurs spécifiques.',
    ],
    'table' => [
        'name' => 'Nom',
        'roles_count' => 'Rôles',
        'users_count' => 'Utilisateurs',
        'created_at' => 'Date de création',
        'updated_at' => 'Dernière mise à jour',
    ],
    'infolist' => [
        'name' => 'Nom',
        'roles' => 'Rôles',
        'created_at' => 'Date de création',
        'updated_at' => 'Dernière mise à jour',
    ],
    'filters' => [
        'module' => '🧩 Module',
    ],
    'grouping' => [
        'module' => '🧩 Module',
    ],
];
