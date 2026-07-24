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
];
