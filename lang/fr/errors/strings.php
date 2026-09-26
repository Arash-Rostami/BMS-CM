<?php

return [
    'codes' => [
        401 => [
            'title' => 'Non autorisé',
            'message' => 'Vous devez vous connecter pour accéder à cette page.',
        ],
        403 => [
            'title' => 'Accès interdit',
            'message' => "Vous n'avez pas la permission d'accéder à cette page.",
        ],
        404 => [
            'title' => 'Page introuvable',
            'message' => "La page que vous recherchez n'existe pas ou a été déplacée.",
        ],
        419 => [
            'title' => 'Session expirée',
            'message' => 'Votre session a expiré pour des raisons de sécurité. Veuillez actualiser la page et réessayer.',
        ],
        429 => [
            'title' => 'Trop de requêtes',
            'message' => 'Vous avez effectué trop de requêtes en peu de temps. Veuillez patienter puis réessayer.',
        ],
        500 => [
            'title' => 'Erreur serveur',
            'message' => "Une erreur s'est produite de notre côté. Notre équipe a été notifiée et travaille sur le problème.",
        ],
        503 => [
            'title' => 'Maintenance en cours',
            'message' => 'Nous effectuons une maintenance planifiée. Veuillez revenir sous peu.',
        ],
    ],
    'error_label' => 'Erreur',
    'occurred_at' => 'Survenu le',
    'go_to_dashboard' => 'Aller au tableau de bord',
    'go_back' => 'Retour',

    'notifications' => [
        'reference_line' => 'Référence : :ref — merci de la transmettre au support si le problème persiste.',
        'number_too_large_title' => 'Nombre trop grand',
        'number_too_large_body' => "L'une des valeurs saisies dépasse la capacité de ce champ. Veuillez vérifier le montant et réessayer.",
        'value_too_long_title' => 'Texte trop long',
        'value_too_long_body' => "L'une des valeurs saisies dépasse la longueur autorisée pour ce champ. Veuillez la raccourcir et réessayer.",
        'invalid_format_title' => 'Valeur inattendue',
        'invalid_format_body' => "L'une des valeurs ne correspond pas au format attendu pour ce champ. Veuillez vérifier vos saisies et réessayer.",
        'missing_required_title' => 'Information manquante',
        'missing_required_body' => "Une information obligatoire n'a pas été fournie. Veuillez remplir tous les champs requis et réessayer.",
        'duplicate_title' => 'Déjà existant',
        'duplicate_body' => 'Un enregistrement avec cette même valeur existe déjà dans le système.',
        'fk_in_use_title' => 'Encore utilisé',
        'fk_in_use_body' => "Cet enregistrement ne peut pas être modifié ou supprimé car d'autres enregistrements en dépendent encore.",
        'fk_invalid_reference_title' => 'Sélection indisponible',
        'fk_invalid_reference_body' => "L'un des éléments sélectionnés n'existe plus. Veuillez actualiser la page et choisir à nouveau.",
        'busy_retry_title' => 'Système occupé',
        'busy_retry_body' => 'Le système traitait momentanément une autre demande. Veuillez réessayer d\'enregistrer.',
        'connection_lost_title' => 'Connexion interrompue',
        'connection_lost_body' => 'La connexion à la base de données a été interrompue. Veuillez réessayer dans un instant.',
        'system_config_title' => 'Problème de configuration détecté',
        'system_config_body' => 'Un problème de configuration du système a été détecté. Veuillez contacter le support et indiquer la référence ci-dessous.',
        'database_title' => "Échec de l'enregistrement",
        'database_body' => "Le système n'a pas pu enregistrer vos modifications en raison d'une erreur de base de données. Veuillez réessayer, et contacter le support si le problème persiste.",
        'not_found_title' => 'Enregistrement introuvable',
        'not_found_body' => "Cet enregistrement n'existe plus — il a peut-être été supprimé ou modifié par quelqu'un d'autre. Veuillez actualiser la page.",
        'forbidden_title' => 'Action non autorisée',
        'forbidden_body' => "Vous n'avez pas la permission d'effectuer cette action.",
        'session_expired_title' => 'Session expirée',
        'session_expired_body' => 'Votre session a expiré. Veuillez actualiser la page et réessayer.',
        'payload_too_large_title' => 'Volume de données trop important',
        'payload_too_large_body' => 'Le fichier ou le volume de données envoyé est trop important. Veuillez le réduire et réessayer.',
        'generic_title' => "Une erreur s'est produite",
        'generic_body' => "Une erreur inattendue s'est produite lors du traitement de votre demande. Veuillez réessayer, et contacter le support si le problème persiste.",
    ],
];
