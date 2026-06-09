<?php

return [
    'general' => [
        'model_label' => 'Correspondance',
        'plural_model_label' => 'Correspondances',
        'navigation_group' => 'Données Opérationnelles',
        'create_new' => '˙⋆✮ Rédiger',
    ],
    'enums' => [
        'type' => [
            'report' => 'Rapport',
            'inquiry' => 'Demande',
            'warning' => 'Avertissement',
            'note' => 'Note',
            'announcement' => 'Annonce',
        ],
        'priority' => [
            'low' => 'Basse',
            'normal' => 'Normale',
            'high' => 'Haute',
            'urgent' => 'Urgente',
        ],
    ],
    'form' => [
        'section_main' => 'Contenu du Message',
        'section_settings' => 'Paramètres et Visibilité',
        'section_recipients' => 'Audience et Mentions',

        'subject' => 'Sujet',
        'body' => 'Corps du message',
        'type' => 'Type',
        'priority' => 'Priorité',
        'status' => 'Statut',
        'is_internal' => 'Note Interne',
        'is_private' => 'Privé (Restreint)',
        'parent' => 'Répondre à',

        'recipients' => 'Destinataires',
        'recipients_to' => 'À (Action Requise)',
        'recipients_cc' => 'CC (Pour Information)',

        'helper_internal' => 'Visible uniquement par le personnel interne.',
        'helper_private' => 'Visible UNIQUEMENT par vous et les destinataires sélectionnés.',
        'helper_subject' => 'Restez court et descriptif pour que les destinataires repèrent le message d\'un coup d\'œil.',

        'validation_required' => 'Le champ :attribute est requis.',
        'validation_unique' => 'Cette valeur existe déjà.',
        'validation_subject_max' => 'Le sujet ne doit pas dépasser 255 caractères.',
            'helper_priority' => 'Définissez l\'urgence de ce message. Une priorité élevée alerte les destinataires.',
    ],
    'table' => [
        'subject' => 'Sujet',
        'type' => 'Type',
        'priority' => 'Priorité',
        'status' => 'Statut',
        'visibility' => 'Visibilité',
        'recipients' => 'Destinataires',
        'creator' => 'Créateur',
        'updater' => 'Modifié par',
        'created_at' => 'Créé le',
        'updated_at' => 'Modifié le',
        'thread' => 'Fil de discussion',
        'thread_prefix' => 'Fil : ',
    ],
    'filters' => [
        'type' => 'Type',
        'priority' => 'Priorité',
        'status' => 'Statut',
        'creator' => 'Auteur',
        'my_mentions' => 'Mes Mentions (Boîte de réception)',
        'created_from' => 'Créé depuis',
        'created_until' => 'Créé jusqu\'à',
    ],
    'infolist' => [
        'tab_general' => 'Détails du message',
        'attachments' => 'Pièces jointes',
        'creator' => 'Créé par',
    ],
    'export' => [
        'filename' => 'export-correspondance',
    ]
];
