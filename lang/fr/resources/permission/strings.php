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
        'validation_name_regex' => 'Le nom doit suivre le format module.action, avec uniquement des lettres anglaises minuscules, des chiffres et des traits de soulignement (ex. purchase_request.view).',
        'validation_roles_in' => 'Veuillez choisir uniquement des rôles valides.',
        'validation_users_in' => 'Veuillez choisir uniquement des utilisateurs valides.',
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
        'users' => 'Utilisateurs',
        'name' => 'Nom',
        'roles' => 'Rôles',
        'created_at' => 'Date de création',
        'updated_at' => 'Dernière mise à jour',
    ],
    'actions' => [
        'delete_warning' => 'Cet accès sera retiré à :roles rôle(s) et à :users utilisateur(s).',
    ],
    'filters' => [
        'module' => '🧩 Module',
        'ungranted' => '🚫 Accordée à personne',
        'ungranted_indicator' => 'Accordée à personne',
    ],
    'grouping' => [
        'module' => '🧩 Module',
    ],
    'export' => [
        'export_permissions' => 'Exporter les Permissions',
        'id' => 'ID',
        'name' => 'Nom',
        'roles_count' => 'Nombre de rôles',
        'users_count' => 'Nombre d\'utilisateurs',
        'created_at' => 'Date de création',
        'updated_at' => 'Dernière mise à jour',
    ],
];
