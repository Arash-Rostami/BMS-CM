<?php

return [
    'welcome' => 'Bienvenue',
    'reset_cache' => [
        'label' => 'Réinitialiser le cache',
        'success' => 'Cache réinitialisé avec succès.',
    ],
    'actions' => [
        'view_tooltip' => 'Voir',
        'edit_tooltip' => 'Modifier',
        'delete_tooltip' => 'Supprimer',
        'restore_tooltip' => 'Restaurer',
        'create' => 'Créer',
        'add_record' => '˙⋆✮ Créer Nouveau',
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
        'attachment_name' => 'Nom de la pièce jointe',
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
        'error_title' => 'Erreur',
        'error_body' => 'Une erreur s\'est produite lors du traitement des pièces jointes.',
        'warning_title' => 'Avertissement',
    ],
    'export' => [
        'completed' => 'Votre exportation est terminée et :successful ligne(s) exportée(s).',
        'failed' => ' :failed ligne(s) échouée(s).',
    ],
    'metrics' => [
        'mt' => 'Tonne métrique',
        'kg' => 'Kilogramme',
        'lb' => 'Livre',
        'oz' => 'Once',
        'm3' => 'Mètre cube',
        'ft3' => 'Pied cube',
        'l' => 'Litre',
        'gal' => 'Gallon',
        'pcs' => 'Pièces',
        'unit' => 'Unité',
    ],
    'calendar_toggle' => [
        'switch_to_gregorian' => 'Passer au grégorien',
        'switch_to_jalali' => 'Passer au jalali',
        'jalali_abbr' => 'HS',
        'gregorian_abbr' => 'EC',
    ],
    'nav_dock' => [
        'switch_to_bottom' => 'Passer à la barre inférieure',
        'switch_to_side' => 'Passer à la barre latérale',
    ],
    'topbar_pin' => [
        'pin' => 'Épingler la barre supérieure (désactiver le masquage automatique)',
        'unpin' => 'Désépingler la barre supérieure (activer le masquage automatique)',
    ],
    'manage_custom_attributes' => [
        'label' => 'Gérer les attributs personnalisés',
        'modal_heading' => 'Gérer les attributs personnalisés',
        'save' => 'Enregistrer',
        'key_label' => 'Clé',
        'value_label' => 'Valeur',
        'add_row' => 'Ajouter une ligne',
    ],
    'extra_attributes' => [
        'key' => 'Clé',
        'value' => 'Valeur',
        'add_action' => 'Ajouter un attribut',
    ],
    'desk_reference' => [
        'tab_label' => 'Guide de Référence',
        'listen_prompt' => 'Préférez-vous écouter ?',
        'watch_prompt' => 'Préférez-vous regarder ?',
        'search_placeholder' => 'Rechercher dans ce guide…',
        'terms_heading' => 'Terminologie',
        'process_heading' => 'Processus',
        'dos_donts_heading' => 'À faire & à ne pas faire',
        'disclaimer' => "Conseils opérationnels, révisés :date. Les réglementations changent — confirmez les règles en vigueur avant d'agir.",
        'action_label' => 'Guide de Référence',
        'modal_heading' => 'Guide de Référence',
        'tab_text' => 'Référence',
        'tab_media' => 'Médias',
        'video_heading' => 'Vidéo',
        'tips_heading' => 'Conseils clés',
    ],
    'greetings' => [
        'morning_saturday' => [
            "Bonjour {name}, un début de semaine solide s'annonce.",
            'Bonjour {name}, prêt pour une semaine productive.',
        ],
        'afternoon_saturday' => [
            'Bon après-midi {name}, la semaine démarre bien.',
            'Bonjour {name}, le rythme reste constant.',
        ],
        'evening_saturday' => [
            'Bonsoir {name}, une première journée productive.',
            'Bonsoir {name}, un bon départ pour la semaine.',
        ],
        'night_saturday' => [
            'Bonne nuit {name}, reposez-vous pour repartir en force demain.',
            'Bonne nuit {name}, reposez-vous bien.',
        ],

        'morning_sunday' => [
            'Bonjour {name}, la régularité fait la différence.',
            "Bonjour {name}, la concentration d'aujourd'hui prépare celle de demain.",
        ],
        'afternoon_sunday' => [
            'Bon après-midi {name}, la journée avance bien.',
            'Bonjour {name}, une courte pause peut faire la différence.',
        ],
        'evening_sunday' => [
            "Bonsoir {name}, une deuxième journée productive s'achève.",
            'Bonsoir {name}, la constance porte ses fruits.',
        ],
        'night_sunday' => [
            'Bonne nuit {name}, reposez-vous bien.',
            'Bonne nuit {name}, demain commence sur de bonnes bases.',
        ],

        'morning_monday' => [
            "Bonjour {name}, les priorités d'aujourd'hui méritent toute votre attention.",
            'Bonjour {name}, la concentration fait toute la différence.',
        ],
        'afternoon_monday' => [
            'Bon après-midi {name}, continuez sur cette lancée.',
            'Bonjour {name}, chaque tâche terminée est un pas en avant.',
        ],
        'evening_monday' => [
            'Bonsoir {name}, deux journées productives derrière vous.',
            "Bonsoir {name}, du bon travail aujourd'hui.",
        ],
        'night_monday' => [
            'Bonne nuit {name}, vous avez mérité du repos.',
            'Bonne nuit {name}, demain sera encore meilleur.',
        ],

        'morning_tuesday' => [
            'Bonjour {name}, cette journée pourrait être la plus productive de la semaine.',
            'Bonjour {name}, traitez les priorités tôt pour plus de sérénité.',
        ],
        'afternoon_tuesday' => [
            'Bon après-midi {name}, vous avez passé le milieu de la semaine.',
            'Bonjour {name}, une courte pause avant de continuer avec concentration.',
        ],
        'evening_tuesday' => [
            'Bonsoir {name}, la partie la plus exigeante de la semaine est derrière vous.',
            "Bonsoir {name}, une contribution solide aujourd'hui.",
        ],
        'night_tuesday' => [
            'Bonne nuit {name}, le milieu de la semaine approche.',
            "Bonne nuit {name}, une soirée calme s'annonce.",
        ],

        'morning_wednesday' => [
            'Bonjour {name}, vous avez dépassé la moitié du parcours.',
            'Bonjour {name}, la progression constante continue de porter ses fruits.',
        ],
        'afternoon_wednesday' => [
            'Bon après-midi {name}, les résultats prennent forme.',
            'Bonjour {name}, un travail de qualité se construit souvent en silence.',
        ],
        'evening_wednesday' => [
            'Bonsoir {name}, les trois quarts de la semaine sont accomplis.',
            "Bonsoir {name}, les efforts d'aujourd'hui se refléteront demain.",
        ],
        'night_wednesday' => [
            "Bonne nuit {name}, il ne reste qu'un jour de travail.",
            'Bonne nuit {name}, vous terminez la semaine sur une bonne dynamique.',
        ],

        'morning_thursday' => [
            'Bonjour {name}, la journée est propice pour clore les dossiers en cours.',
            'Bonjour {name}, une bonne conclusion commence par un plan clair.',
        ],
        'afternoon_thursday' => [
            'Bon après-midi {name}, une bonne fin se construit avec soin.',
            "Bonjour {name}, les résultats d'aujourd'hui se prolongeront la semaine prochaine.",
        ],
        'evening_thursday' => [
            "Bonsoir {name}, la dernière journée ouvrable de la semaine s'achève bien.",
            'Bonsoir {name}, une fin méritée pour une semaine productive.',
        ],
        'night_thursday' => [
            'Bonne nuit {name}, le travail de la semaine est terminé.',
            'Bonne nuit {name}, profitez du temps qui vient.',
        ],

        'morning_friday' => [
            "Bonjour {name}, profitez d'un rythme plus calme aujourd'hui.",
            'Bonjour {name}, profitez de votre journée de repos.',
        ],
        'afternoon_friday' => [
            'Bon après-midi {name}, un bon moment pour recharger vos batteries.',
            'Bonjour {name}, profitez pleinement de ce repos mérité.',
        ],
        'evening_friday' => [
            'Bonsoir {name}, une fin de semaine paisible.',
            'Bonsoir {name}, un bon moment pour revenir sur la semaine.',
        ],
        'night_friday' => [
            "Bonne nuit {name}, la nouvelle semaine n'a pas encore commencé, reposez-vous bien.",
            'Bonne nuit {name}, une bonne semaine vous attend.',
        ],
    ],
];
