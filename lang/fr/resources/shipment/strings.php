<?php

return [
    'general' => [
        'model_label' => 'Expédition',
        'plural_model_label' => 'Expéditions',
        'navigation_group' => 'Données Opérationnelles',
        'add_record' => '˙⋆✮ Créer Nouveau',
    ],
    'form' => [
        'tabs' => [
            'general' => 'Informations Générales',
            'logistics' => 'Logistique & Détails',
        ],
        'section_general' => 'Informations Principales',
        'section_docs_notes' => 'Documents & Pièces Jointes',
        'section_dates' => 'Calendrier',
        'section_logistics' => 'Données Logistiques',
        'section_status' => 'Vue d\'ensemble du Statut',
        'section_amounts' => 'Montants & Quantités',

        'shipment_no' => 'N° Expédition',
        'registered_order' => 'Commande Enregistrée',
        'contract_no' => 'N° de Contrat',
        'carrier' => 'Transporteur',
        'part' => 'Partie',
        'case_number' => 'Numéro de Dossier',

        'warehouse_date' => 'Date du reçu d\'entrepôt',
        'exit_date' => 'Date de Sortie',
        'eta' => 'ETA',
        'etd' => 'ETD',

        'bl_number' => 'N° BL',
        'booking_no' => 'N° Booking',
        'container_no' => 'Nombre de Conteneurs',
        'container_type' => 'Type de Conteneur',
        'container_types_with_opt' => [
            'Standard' => [
                '20ft Standard' => '20ft Standard',
                '40ft Standard' => '40ft Standard',
                '40ft High Cube' => '40ft High Cube',
            ],
            'Frigorifique' => [
                '20ft Refrigerated' => '20ft Frigorifique',
                '40ft Refrigerated' => '40ft Frigorifique',
            ],
            'Open Top' => [
                '20ft Open Top' => '20ft Open Top',
                '40ft Open Top' => '40ft Open Top',
            ],
            'Flat Rack' => [
                '20ft Flat Rack' => '20ft Flat Rack',
                '40ft Flat Rack' => '40ft Flat Rack',
            ],
            'Autres' => [
                'LCL' => 'LCL (Groupage)',
                'Bulk' => 'Vrac',
            ],
        ],
        'container_types' => [ '20ft Standard' => '20ft Standard', '40ft Standard' => '40ft Standard', '40ft High Cube' => '40ft High Cube', '20ft Refrigerated' => '20ft Frigorifique', '40ft Refrigerated' => '40ft Frigorifique', '20ft Open Top' => '20ft Open Top', '40ft Open Top' => '40ft Open Top', '20ft Flat Rack' => '20ft Flat Rack', '40ft Flat Rack' => '40ft Flat Rack', 'LCL' => 'LCL (Groupage)', 'Bulk' => 'Vrac', ],


        'remittance_amount' => 'Montant Remise',
        'customs_quantity' => 'Qté restante en douane',
        'shipped_quantity' => 'Qté Expédiée',

        'status' => 'Statut Principal',
        'shipment_status' => 'Statut de Suivi',
        'operation_status' => 'Statut Opérationnel',
        'container_status' => 'Statut Conteneur',
        'guarantee_status' => 'Statut Garantie',
        'doc_status' => 'Statut Document',

        'docs' => 'Liste de Contrôle des Documents',
        'doc_name' => 'Nom du Document',
        'doc_name_placeholder' => 'ex. Certificat d\'Inspection',
        'doc_received' => 'Reçu',
        'add_doc' => 'Ajouter un Document',
        'docs_options' => [
            'inspection' => 'Certificat d\'Inspection',
            'bank_commitment' => 'Engagement Bancaire',
            'insurance' => 'Police d\'Assurance',
            'ci' => 'Facture Commerciale (CI)',
            'pl' => 'Liste de Colisage (PL)',
            'co' => 'Certificat d\'Origine (CO)',
            'bl' => 'Connaissement (BL)',
            'do' => 'Bon de Livraison (DO)',
        ],
        'attachments' => 'Pièces Jointes',
        'notes' => 'Notes Internes',

        'validation' => [
            'required' => 'Ce champ est obligatoire.',
            'unique' => 'Cette valeur doit être unique.',
            'unique_part' => 'Cette partie a déjà été enregistrée pour cette commande et ce contrat.',
            'english_only' => 'Seuls les lettres anglaises, chiffres, parenthèses et tirets sont autorisés.',
        ],
    ],
    'table' => [
        'id' => 'ID',
        'shipment_no' => 'N° Expédition',
        'registered_order' => 'Lié aux',
        'carrier' => 'Transporteur',
        'part' => 'Partie',
        'bl_number' => 'N° BL',
        'booking_no' => 'N° Booking',
        'container_no' => 'Qté Cont.',
        'container_status' => 'Statut Cont.',
        'eta' => 'ETA',
        'status' => 'Statut',
        'tracking_status' => 'Suivi',
        'created_by' => 'Créé par',
        'created_at' => 'Créé le',
        'updated_by' => 'Mis à jour par',
        'updated_at' => 'Mis à jour le',
    ],
    'filters' => [
        'created_from' => 'Créé depuis',
        'created_until' => 'Créé jusqu\'à',
        'eta_from' => 'ETA de',
        'eta_until' => 'ETA à',
    ],
    'infolist' => [
        'tab_general'   => 'Informations générales',
        'tab_logistics' => 'Logistique',
        'tab_docs'      => 'Données maîtres',
        'tab_documents' => 'Pièces jointes',
    ],
];
