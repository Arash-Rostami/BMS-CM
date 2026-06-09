<?php

return [
    'general' => [
        'model_label' => 'Rôle',
        'plural_model_label' => 'Rôles',
    ],
    'form' => [
        'name' => 'Nom',
        'grade' => 'niveau',
        'permissions' => 'Permissions',
        'select_all' => 'Sélectionner toutes les permissions (Super Admin)',
        'modules' => 'Accorder un accès complet par module',
        'helper_name' => 'Utilisez uniquement des lettres anglaises et des traits de soulignement ; le nom est automatiquement converti en snake_case.',
        'validation_name_required' => 'Veuillez saisir un nom pour ce rôle.',
        'validation_name_unique' => 'Un rôle portant ce nom existe déjà.',
        'validation_name_regex' => 'Le nom ne peut contenir que des lettres anglaises et des traits de soulignement, sans espaces ni chiffres.',
        'validation_grade_required' => 'Veuillez sélectionner un niveau pour ce rôle.',
        'validation_name_max' => 'Le nom du rôle ne peut pas dépasser 255 caractères.',
            'helper_grade' => 'Sélectionnez le niveau d\'ancienneté pour préfixer automatiquement le rôle.',
            'helper_modules' => 'Sélectionnez des modules pour attribuer rapidement des autorisations en masse.',
    ],
    'table' => [
        'name' => 'Nom',
        'permissions_count' => 'Nombre de permissions',
        'users_count' => 'Nombre d\'utilisateurs',
        'created_at' => 'Date de création',
        'updated_at' => 'Dernière mise à jour',
    ],
    'infolist' => [
        'name' => 'Nom',
        'grade' => 'niveau',
        'permissions' => 'Permissions',
        'created_at' => 'Date de création',
        'updated_at' => 'Dernière mise à jour',
    ],
    'filters' => [
        'name' => 'Nom',
        'grade' => 'Niveau',
    ],
    'grouping' => [
        'base_name' => 'Nom',
        'grade' => 'Niveau',
    ],
];
