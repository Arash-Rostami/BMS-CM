<?php
return [
    'status' => [
        'active' => 'Actif',
    ],
    'steps' => [
        'request_approval' => [
            'title' => 'Demande et approbation',
            'description' => '❶ Début du pré-processus : créer une demande d\'achat ou enregistrer une facture pro forma',
            'pending_requests' => 'Demandes d\'achat',
        ],
        'order_processing' => [
            'title' => 'Traitement des commandes',
            'description' => '❷ Début du processus : enregistrer la commande et créer un profil bancaire',
            'active_orders' => 'Commandes actives',
        ],
        'procurement_payment' => [
            'title' => 'Approvisionnement et paiement',
            'description' => '❸ Suite du processus : créer la commande d\'achat et traiter les paiements',
            'pending_payments' => 'Paiements en attente',
        ],
        'logistics' => [
            'title' => 'Logistique et dédouanement',
            'description' => '❹ Finalisation du processus : créer et traiter les données d\'expédition, la logistique et le dédouanement',
        ],
    ],
    'view_requests' => 'Voir les demandes',
    'view_orders' => 'Voir les commandes',
    'proforma' => 'Voir les facture proformas',
    'banks' => 'Voir les banques',
    'payments' => 'Voir les paiements',
    'coming_soon' => 'Bientôt',
    'submodules' => [
        'shipment' => [
            'title' => 'Expédition',
            'description' => 'Suivi des envois',
        ],
        'logistics' => [
            'title' => 'Logistique',
            'description' => "Gestion des opérations",
        ],
        'custom_clearance' => [
            'title' => 'Dédouanement',
            'description' => 'Gestion des formalités douanières',
        ],
    ],
    'show_submodules' => 'Afficher les sous-modules',
    'hide_submodules' => 'Masquer les sous-modules',
    'flow' => [
        'request' => 'Demande',
        'order' => 'Commande',
        'payment' => 'Paiement',
        'logistics' => 'Logistique',
    ],
    'bms_dashboard' => 'BMS',
    'return_to_main_panel' => 'Retour au tableau de bord principal',
];
