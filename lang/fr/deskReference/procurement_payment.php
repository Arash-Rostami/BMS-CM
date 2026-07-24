<?php

return [
    'tab_label' => 'Guide de Référence : Devises et Paiement',
    'scope_label' => 'Étape d\'approvisionnement en devises, de paiement du fournisseur et de levée de l\'engagement de change',
    'covers' => ['purchaseOrder', 'payment'],

    'tips' => [
        'Lever l\'engagement de change dans le délai légal (3 mois, prolongeable jusqu\'à 6) est obligatoire ; sinon le renouvellement de la carte commerciale est suspendu.',
    ],

    'terms' => [
        ['term' => 'Centre d\'échange de devises et d\'or d\'Iran', 'definition' => 'Remplacement officiel du système NIMA à partir du 11 Bahman 1403 ; son Marché des devises commerciales est l\'endroit où les devises sont offertes et demandées via le courtage des bourses/banques agentes.'],
        ['term' => 'Rapatriement des devises d\'exportation (Engagement de change)', 'definition' => 'L\'obligation de l\'exportateur de restituer les recettes d\'exportation dans le délai légal via des canaux approuvés ; cette même devise est la principale source d\'allocation des importations.'],
        ['term' => 'Deuxième salle des marchés de devises', 'definition' => 'Une section distincte pour les marchandises du deuxième groupe (matières premières, biens intermédiaires et biens d\'équipement) dont le prix est fixé par accord direct entre l\'exportateur et l\'importateur.'],
        ['term' => 'Banque agente', 'definition' => 'Intermédiaire entre le demandeur et la banque centrale ; reçoit la demande, obtient l\'approbation, prend l\'acte d\'engagement de change et émet la déclaration d\'approvisionnement en devises.'],
        ['term' => 'Déclaration d\'approvisionnement en devises (code SATA)', 'definition' => 'Document émis par la banque agente ; condition préalable pour l\'envoi du dossier à la douane.'],
        ['term' => 'Devises sans transfert', 'definition' => 'Méthode d\'approvisionnement en devises via un enregistrement de commande "sans transfert de devises" en échange d\'importations réciproques contre les exportations de tiers.'],
        ['term' => 'Types de devises', 'definition' => 'Taux découvert par le marché sur le Marché des devises commerciales (basé sur l\'offre et la demande), gouvernemental/préférentiel (biens essentiels), et libre.'],
    ],

    'process' => [
        ['title' => 'Soumettre une demande d\'allocation de devises', 'description' => 'Par l\'intermédiaire de la banque agente.'],
        ['title' => 'Approbation de la banque centrale', 'description' => 'Et prise de l\'engagement de rapatriement/restitution des devises par l\'importateur.'],
        ['title' => 'Fournir les devises d\'importation', 'description' => 'Acheter sur le Marché des devises commerciales, ou utiliser ses propres recettes d\'exportation, ou via un enregistrement de commande "sans transfert de devises".'],
        ['title' => 'Émettre la déclaration d\'approvisionnement en devises (SATA)', 'description' => 'Par la banque agente.'],
        ['title' => 'Transférer les fonds au fournisseur', 'description' => 'T/T (virement bancaire), L/C (lettre de crédit) ou la méthode convenue dans la proforma.'],
        ['title' => 'Lever l\'engagement de change', 'description' => 'Soumettre le permis de douane et les documents à la banque agente dans le délai légal.'],
    ],

    'dos' => [
        'Utiliser le nom à jour "Centre d\'échange de devises / Marché des devises commerciales" au lieu du "système NIMA" dans les documents internes.',
        'Lever l\'engagement de change dans le délai légal (3 mois, prolongeable jusqu\'à 6 mois supplémentaires avec l\'approbation de la banque).',
        'Afficher les statuts "délai restant de l\'engagement de change" et "documents envoyés à la banque" pour chaque dossier.',
    ],

    'donts' => [
        'Omettre de lever l\'engagement à temps — entraîne la suspension du renouvellement de la carte commerciale, des amendes ou une action de la banque centrale.',
        'Faire référence au "système NIMA" dans les documents internes — il a été effectivement aboli depuis Bahman 1403.',
    ],

    'as_of' => 'Juillet 2026',

    'media' => [
        'video' => 'procurement_payment-fa.mp4',
        'audio' => 'procurement_payment-fa.m4a',
        'poster' => 'procurement_payment-fa.jpg',
    ],
];
