<?php

return [
    'general' => [
        'model_label' => 'Notification',
        'plural_model_label' => 'Notifications',
        'navigation_group' => 'Notifications',
        'add_record' => '˙⋆✮ Créer un nouveau paramètre de notification',
    ],

    'form' => [
        'tabs' => [
            'general' => 'Informations générales',
            'filters' => 'Filtres & Colonnes',
            'notifications' => 'Paramètres de notification',
        ],

        'section_general' => 'Détails de la notification',
        'section_filters' => 'Sélection de la table et des colonnes',
        'section_notifications' => 'Préférences de notification',

        'tables' => 'Sélectionnez les tables à surveiller',
        'columns' => 'Sélectionnez les colonnes à suivre',
        'column_values' => 'Sélectionnez les valeurs des colonnes',
        'users' => 'Sélectionnez les utilisateurs à notifier',
        'notification_type' => 'Canal de notification',
        'description' => 'Description',
        'is_active' => 'Actif',
        'notes' => 'Notes supplémentaires',
        'actions' => 'Actions',

        'tables_description' => '⚡ Basé sur les tables, les colonnes et les valeurs qui changent.',

        'validation_required' => 'Ce champ est obligatoire.',
        'validation_numeric' => 'Ce champ doit être un nombre.',
        'validation_date' => 'Veuillez entrer une date valide.',
        'validation_notes_max' => 'Veuillez limiter vos notes à 500 caractères maximum.',

        'helper_notes' => 'Utilisez cet espace pour un contexte rapide, mais gardez-le sous 500 caractères.',
            'helper_actions' => 'Sélectionnez les actions de base de données qui déclenchent cette notification.',
            'helper_columns' => 'Déclencher la notification uniquement lorsque ces colonnes spécifiques changent.',
            'helper_column_values' => 'Déclencher la notification uniquement lorsque les colonnes changent pour ces valeurs spécifiques.',
    ],

    'table' => [
        'tables' => 'Table',
        'columns' => 'Colonnes',
        'column_values' => 'Valeurs des colonnes',
        'users' => 'Utilisateurs',
        'notification_type' => 'Type',
        'actions' => 'Actions',
        'is_active' => 'Actif',
        'created_by' => 'Créé par',
        'updated_by' => 'Mis à jour par',
        'created_at' => 'Créé le',
        'updated_at' => 'Mis à jour le',
    ],

    'filters' => [
        'actions' => 'Actions',
        'tables' => 'Tables',
        'columns' => 'Colonnes',
        'users' => 'Utilisateurs',
        'notification_type' => 'Canal',
        'is_active' => 'Statut Actif',
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'creator' => 'Créé Par',
        'updater' => 'Mis à Jour Par',
        'created_from' => 'Créé Depuis',
        'created_until' => 'Créé Jusqu\'à',
    ],

    'infolist' => [
        'tab_general' => 'Informations générales',
        'tab_filters' => 'Filtres & Colonnes',
        'tab_notifications' => 'Notifications',
        'tables' => 'Tables',
        'actions' => 'Actions',
        'columns' => 'Colonnes',
        'column_values' => 'Valeurs de colonnes',
        'users' => 'Utilisateurs',
        'notification_type' => 'Canal',
        'is_active' => 'Actif',
        'notes' => 'Notes',
        'description' => 'Description',
        'created_by' => 'Créé par',
        'updated_by' => 'Mis à jour par',
        'created_at' => 'Créé le',
        'updated_at' => 'Mis à jour le',
    ],
];
