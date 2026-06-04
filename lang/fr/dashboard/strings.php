<?php
return [
    'status' => [
        'active' => 'Actif',
    ],
    'resources' => [
        'banks' => 'Banques',
        'companies' => 'Entreprises',
        'statuses' => 'Statuts',
        'products' => 'Produits',
        'users' => 'Utilisateurs',
        'categories' => 'Catégories',
        'targets' => 'Objectifs',
        'currencies' => 'Devises',
        'purchase_requests' => 'Demandes d\'achat',
        'purchase_orders' => 'Commandes d\'achat',
        'proforma_invoices' => 'Factures proforma',
        'payments' => 'Paiements',
        'roles' => 'Rôles',
        'permissions' => 'Autorisations',
        'notification_settings' => 'Notifications',
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
        ],
        'logistics' => [
            'title' => 'Logistique et dédouanement',
            'description' => '❹ Finalisation du processus : gérer les expéditions et le dédouanement',
        ],
    ],
    'view_requests' => 'Voir les demandes',
    'view_orders' => 'Voir les commandes',
    'purchase_orders' => 'Bons de commande',
    'proforma' => 'Voir les facture proformas',
    'banks' => 'Voir les banques',
    'payments' => 'Voir les paiements',
    'submodules' => [
        'shipment' => [
            'title' => 'Expéditions',
            'description' => 'Suivi des envois',
        ],
        'custom_clearance' => [
            'title' => 'Dédouanement',
            'description' => 'Gestion des formalités douanières',
        ],
    ],
    'flow' => [
        'request' => 'Demande',
        'order' => 'Commande',
        'payment' => 'Paiement',
        'logistics' => 'Logistique',
    ],
    'bms_dashboard' => 'BMS',
    'return_to_main_panel' => 'Retour au tableau de bord principal',
    'workspace' => 'Votre espace de travail',
    'edit_workspace' => 'Personnaliser les raccourcis',
    'save' => 'Sauvegarder les modifications',
    'add_shortcut' => 'Ajouter un raccourci',
    'remove_shortcut' => 'Supprimer le raccourci',
    'available_shortcuts' => 'Raccourcis disponibles',
    'active_shortcuts' => 'Raccourcis actifs',
];
