<?php

return [
    'actions' => [
        'view_tooltip' => 'Voir',
        'edit_tooltip' => 'Modifier',
        'delete_tooltip' => 'Supprimer',
        'restore_tooltip' => 'Restaurer',
        'create' => 'Créer',
        'view' => 'Voir',
        'edit' => 'Mettre à jour',
        'delete' => 'Supprimer',
    ],
    'bulk' => [
        'activate' => [
            'label' => 'Activer',
            'notification' => 'Les éléments sélectionnés ont été activés avec succès.',
        ],
        'deactivate' => [
            'label' => 'Désactiver',
            'notification' => 'Les éléments sélectionnés ont été désactivés avec succès.',
        ],
    ],
    'relevant_module' => [
        'form' => [
            'purchase_requests' => "Demandes d'achat",
            'purchase_orders' => "Commandes d'achat",
            'proforma_invoices' => 'Factures proforma',
            'registered_orders' => 'Commandes enregistrées',
            'related_to' => 'Lié à',
            'purchase_requests_related' => "Demandes d'achat",
            'purchase_orders_related' => "Commandes d'achat",
            'proforma_invoices_related' => 'Factures proforma',
            'registered_orders_related' => 'Commandes enregistrées',
        ],
        'table' => [
            'related_to' => 'Lié à',
            'purchase_requests' => "Demandes d'achat liées",
            'purchase_orders' => "Commandes d'achat liées",
            'proforma_invoices' => 'Factures proforma liées',
            'registered_orders' => 'Commandes enregistrées liées',
        ],
    ],
    'attachments' => [
        'attachments' => 'Pièces jointes',
        'attachment_name' => "Nom de la pièce jointe",
        'item_attachments' => "Pièces jointes de l'article",
        'has_item_attachments' => "A des pièces jointes d'article",
        'add_item_attachments' => "Ajouter des pièces jointes d'article",

        'validation' => [
            'attachments_max_files' => 'Maximum de 10 fichiers autorisés',
            'attachments_type' => 'Format de fichier invalide (images, PDF, Excel)',
            'attachments_size' => 'La taille du fichier ne doit pas dépasser 2,5 Mo',
            'invalid_filename_chars_hint' => 'Veuillez renommer le fichier en utilisant uniquement les caractères autorisés (lettres anglaises, caractères persans, chiffres, espaces, points, tirets ou underscores) et en gardant un nom de fichier raisonnablement court.',
            'invalid_filename_chars' => 'Nom de fichier invalide ou trop long.',
            'file_not_available_hint' => 'Le fichier téléversé n’est plus disponible. Veuillez le téléverser à nouveau.',
            'file_not_available' => 'Le fichier a expiré. Veuillez le téléverser à nouveau.',
            'metadata_unreadable_hint' => 'Impossible de valider le fichier téléversé. Veuillez réessayer.',
            'metadata_unreadable' => 'Échec de la validation du fichier.',
            'processing_failed' => 'Impossible de traiter le fichier joint. Veuillez vérifier son intégrité et réessayer.',
        ],

    ]
];
