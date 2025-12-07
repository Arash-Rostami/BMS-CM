<?php

return [
    'general' => [
        'model_label' => 'Douanes et Dédouanement',
        'plural_model_label' => 'Douanes et Dédouanement',
        'navigation_group' => 'Données Opérationnelles',
        'add_record' => '˙⋆✮ Créer Nouveau',
        'clearance_status' => [
            'declared' => 'Déclaré',
            'documents_submitted' => 'Documents soumis',
            'pending_payment' => 'En attente de paiement',
            'pending_editing' => 'En attente de modification',
            'exit_gate' => 'Porte de sortie',
            'ten_percent_remaining' => '10% Restant',
            'appraisal' => 'Expertise',
            'completed' => 'Terminé',
        ],
        'bank_guarantee_status' => [
            'returned' => 'Retourné',
            'not_returned' => 'Non retourné',
        ],
        'commitment_status' => [
            'completed' => 'Terminé',
            'not_completed' => 'Non terminé',
        ],
    ],
    'form' => [
        'tabs' => [
            'general' => 'Info Générale',
            'dates' => 'Dates Clés',
            'status_financial' => 'Statut & Finances',
        ],
        'section_general' => 'Détails de la Déclaration',
        'section_dates' => 'Chronologie',
        'section_status' => 'Aperçu du Statut',
        'section_financial' => 'Engagements Financiers',
        'section_notes' => 'Notes & Pièces Jointes',

        'custom_no' => 'N° de Douane',
        'registered_order' => 'Commande Enregistrée',
        'shipment' => 'Expédition',
        'contract_no' => 'N° de Contrat',
        'declaration_no' => 'N° de Déclaration',
        'clearance_type' => 'Type de Dédouanement',

        'clearance_date' => 'Date de Dédouanement',
        'doc_submission_date' => 'Date de Soumission des Documents',
        'ten_percent_exit_date' => 'Date de Sortie 10%',
        'payment_due_date' => 'Date d\'Échéance de Paiement',
        'commitment_payment_date' => 'Date de Paiement de l\'Engagement',
        'rial_return_date' => 'Date de Retour Rial',

        'commitment_balance' => 'Solde d\'Engagement',

        'clearance_status' => 'Statut de Dédouanement',
        'bank_guarantee_status' => 'Statut Garantie Bancaire',
        'commitment_status' => 'Statut d\'Engagement',

        'notes' => 'Notes',
        'attachments' => 'Pièces Jointes',

        'validation_required' => 'Ce champ est requis.',
        'validation_numeric' => 'Ce champ doit être un nombre.',
        'validation_date' => 'Veuillez entrer une date valide.',
    ],
    'table' => [
        'declaration_no' => 'N° Déclaration',
        'registered_order' => 'Commande Enregistrée',
        'shipment' => 'Lié aux',
        'contract_no' => 'N° Contrat',
        'clearance_date' => 'Date Dédouanement',
        'clearance_status' => 'Statut',
        'commitment_balance' => 'Solde Eng.',
        'created_by' => 'Créé par',
        'updated_by' => 'Mis à jour par',
        'created_at' => 'Créé le',
        'updated_at' => 'Mis à jour le',
    ],
    'filters' => [
        'contract_no' => 'N° de Contrat',
        'registered_order' => 'Commande Enregistrée',
        'shipment' => 'Expédition',
        'clearance_status' => 'Statut de Dédouanement',
        'bank_guarantee_status' => 'Statut Garantie Bancaire',
        'commitment_status' => 'Statut d\'Engagement',
        'created_from' => 'Créé depuis',
        'created_until' => 'Créé jusqu\'à',
    ],
    'infolist' => [
        'tab_general' => 'Informations Générales',
        'tab_dates_status' => 'Dates et Statut',
        'tab_documents' => 'Pièces Jointes',
    ],
];
