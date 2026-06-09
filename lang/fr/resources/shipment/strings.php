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
        'helper_part' => 'Une même commande peut être expédiée en plusieurs parties — le prochain numéro de partie disponible est suggéré, et seuls les numéros non encore expédiés peuvent être choisis.',
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

            'Refrigerated' => [
                '20ft Refrigerated' => '20ft Frigorifique',

                '40ft Refrigerated' => '40ft Frigorifique',
            ],

            'Other' => [
                'LCL' => 'LCL (Groupage)',

                'Bulk' => 'Vrac',
            ],

            'استاندارد' => [
                '20ft Standard' => '20ft Standard',

                '40ft Standard' => '40ft Standard',

                '40ft High Cube' => '40ft High Cube',
            ],

            'یخچالی' => [
                '20ft Refrigerated' => '20ft Frigorifique',

                '40ft Refrigerated' => '40ft Frigorifique',
            ],

            'روباز' => [
                '20ft Open Top' => '20ft Open Top',

                '40ft Open Top' => '40ft Open Top',
            ],

            'فلت رک' => [
                '20ft Flat Rack' => '20ft Flat Rack',

                '40ft Flat Rack' => '40ft Flat Rack',
            ],

            'سایر' => [
                'LCL' => 'LCL (Groupage)',

                'Bulk' => 'Vrac',
            ],
        ],
        'container_types' => [ '20ft Standard' => '20ft Standard', '40ft Standard' => '40ft Standard', '40ft High Cube' => '40ft High Cube', '20ft Refrigerated' => '20ft Frigorifique', '40ft Refrigerated' => '40ft Frigorifique', '20ft Open Top' => '20ft Open Top', '40ft Open Top' => '40ft Open Top', '20ft Flat Rack' => '20ft Flat Rack', '40ft Flat Rack' => '40ft Flat Rack', 'LCL' => 'LCL (Groupage)', 'Bulk' => 'Vrac', ],


        'remittance_amount' => 'Montant Remise',
        'helper_remittance_amount' => 'Calculé automatiquement : somme du total des remises achetées pour tous les profils bancaires de cette commande.',
        'customs_quantity' => 'Qté restante en douane',
        'shipped_quantity' => 'Qté Expédiée',

        'status' => 'Statut Principal',
        'shipment_status' => 'Statut de Suivi',
        'operation_status' => 'Statut Opérationnel',
        'container_status' => 'Statut Conteneur',
        'guarantee_status' => 'Statut Garantie',
        'doc_status' => 'Statut Document',

        'smart_tracer' => 'Traceur de documents intelligent',
        'smart_tracer_hint' => 'Lorsqu\'il est activé, la liste de contrôle est automatiquement mise à jour à partir des fichiers joints ; lorsqu\'il est désactivé, elle est entièrement manuelle.',
        'docs' => 'Liste de Contrôle des Documents',
        'doc_name' => 'Nom du Document',
        'doc_name_placeholder' => 'ex. Certificat d\'Inspection',
        'doc_received' => 'Reçu',
        'add_doc' => 'Ajouter un Document',
        'docs_options' => [
            'track' => 'Traceur Intelligent',
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
            'numeric' => 'Veuillez saisir un nombre valide ici.',
            'min_numeric_zero' => 'Cette valeur ne peut pas être négative.',
            'max' => 'Veuillez limiter cette valeur à 255 caractères ou moins.',
        ],
            'helper_status' => 'Statut logistique global de l\'expédition.',
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
