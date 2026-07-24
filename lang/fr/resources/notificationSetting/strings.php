<?php

return [
    'general' => [
        'model_label' => 'Notification',
        'plural_model_label' => 'Notifications',
    ],

    'form' => [
        'tables' => 'Sélectionnez les tables à surveiller',
        'columns' => 'Sélectionnez les colonnes à suivre',
        'column_values' => 'Sélectionnez les valeurs des colonnes',
        'users' => 'Sélectionnez les utilisateurs à notifier',
        'notification_type' => 'Canal de notification',
        'is_active' => 'Actif',
        'notes' => 'Notes supplémentaires',
        'actions' => 'Actions',

        'tables_description' => '⚡ Sélectionnez une ou plusieurs ressources. Les colonnes et valeurs disponibles se mettront à jour dynamiquement selon votre choix.',

        'validation_notes_max' => 'Veuillez limiter vos notes à 500 caractères maximum.',

        'helper_notes' => 'Utilisez cet espace pour un contexte rapide, mais gardez-le sous 500 caractères.',
        'helper_actions' => 'Sélectionnez les actions de base de données qui déclenchent cette notification.',
        'helper_columns' => 'Déclencher la notification uniquement lorsque ces colonnes spécifiques changent.',
        'helper_column_values' => 'Déclencher la notification uniquement lorsque les colonnes changent pour ces valeurs spécifiques.',
        'helper_notification_type' => 'Choisissez comment les destinataires sont notifiés. « Les deux » envoie une alerte in-app et un e-mail.',
    ],

    'channels' => [
        'in_app' => 'In-app 💻',
        'email' => 'E-mail 📧',
        'all' => 'Les deux 🔔',
        'unknown' => 'Inconnu',
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
        'notification_type' => 'Canal',
        'is_active' => 'Statut Actif',
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'creator' => 'Créé Par',
        'updater' => 'Mis à Jour Par',
    ],

    'infolist' => [
        'tables' => 'Tables',
        'actions' => 'Actions',
        'columns' => 'Colonnes',
        'column_values' => 'Valeurs de colonnes',
        'users' => 'Utilisateurs',
        'notification_type' => 'Canal',
        'is_active' => 'Actif',
        'notes' => 'Notes',
        'created_by' => 'Créé par',
        'updated_by' => 'Mis à jour par',
        'created_at' => 'Créé le',
        'updated_at' => 'Mis à jour le',
    ],
    'action_types' => [
        'create' => '🟢 Créer',
        'update' => '🟡 Mettre à jour',
        'delete' => '🔴 Supprimer',
    ],
];
