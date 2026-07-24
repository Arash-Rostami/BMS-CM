<?php

return [
    'tab_label' => 'Guide de Référence : Enregistrement de Commande',
    'scope_label' => 'Étape d\'enregistrement de commande et d\'allocation de devises',
    'covers' => ['registeredOrder', 'bankProfile'],

    'tips' => [
        'Les marchandises conditionnelles peuvent rester « en attente de permis » pendant des semaines — planifiez la chronologie du projet en conséquence.',
    ],

    'terms' => [
        ['term' => 'Enregistrement de commande (NTSW)', 'definition' => 'Enregistrement officiel de la commande dans le système commercial global ; condition préalable à l\'allocation de devises et au dédouanement.'],
        ['term' => 'Code SH (code tarifaire)', 'definition' => 'Classification internationale standard des marchandises ; base pour la consultation des règles et le calcul des droits de douane.'],
        ['term' => 'Permis d\'optimisation de l\'utilisation des devises', 'definition' => 'Plafond du ministère de l\'Industrie par code SH pour les importations ; jusqu\'à son approbation, l\'allocation de devises ne peut pas avoir lieu (sauf pour les cas exemptés).'],
        ['term' => 'Quota annuel', 'definition' => 'Moyenne des dédouanements maximums sur les deux dernières années par "commerçant + code tarifaire" ; répartition saisonnière de 30/30/20/20.'],
        ['term' => 'Marché des devises commerciales', 'definition' => 'Remplacement officiel du système NIMA à partir du 11 Bahman 1403 ; allocation de devises via le courtage des bourses/banques agentes.'],
        ['term' => 'Déclaration d\'approvisionnement en devises (code SATA)', 'definition' => 'Résultat de l\'étape d\'allocation de devises ; prépare le dossier pour l\'envoi à la douane.'],
        ['term' => 'Marchandises conditionnelles', 'definition' => 'Marchandises nécessitant un permis (normes, santé, vétérinaire, environnement, etc.) ; le dossier reste "en attente de permis" jusqu\'à la réponse de l\'autorité émettrice.'],
    ],

    'process' => [
        ['title' => 'Se connecter au système commercial global', 'description' => 'En tant que commerçant individuel/société (nécessite une carte commerciale valide).'],
        ['title' => 'Créer un nouveau dossier', 'description' => 'Via "Opérations de commerce extérieur ← Gestion des dossiers d\'enregistrement de commande".'],
        ['title' => 'Compléter les données initiales', 'description' => 'Numéro et date de la proforma, pays bénéficiaire, date de validité de la proforma.'],
        ['title' => 'Télécharger les documents', 'description' => 'Fichier proforma et, si nécessaire, le catalogue technique des marchandises.'],
        ['title' => 'Définir le code tarifaire (code SH)', 'description' => 'Le système exécute automatiquement la "consultation des règles".'],
        ['title' => 'Attendre le permis (si conditionnel)', 'description' => 'Le dossier reste en attente jusqu\'à la réponse de l\'autorité émettrice (par ex. TTAC pour les médicaments et l\'alimentation).'],
        ['title' => 'Payer les frais et émettre le numéro d\'enregistrement', 'description' => 'Après obtention des permis requis, ou si aucun n\'est nécessaire.'],
        ['title' => 'Entrer dans la phase d\'allocation de devises', 'description' => 'Si un approvisionnement officiel en devises est requis.'],
    ],

    'dos' => [
        'Prévoir 3 à 5 jours ouvrables pour l\'enregistrement de commande sans permis spécial.',
        'Concevoir le logiciel de manière à ce que les pourcentages de quota et la formule de calcul soient modifiables sans changement de code.',
        'Calculer et afficher trois valeurs distinctes par "commerçant + code tarifaire" : quota annuel, quota de la saison en cours plus le report, et consommé/restant jusqu\'à la fin de la saison.',
    ],

    'donts' => [
        'Négliger la possibilité d\'un retard de plusieurs semaines pour les marchandises conditionnelles/sensibles.',
        'Les incohérences entre les données des marchandises et la proforma — une des principales causes de retard d\'enregistrement.',
    ],

    'as_of' => 'Juillet 2026',

    'media' => [
        'video' => 'order_processing-fa.mp4',
        'audio' => 'order_processing-fa.m4a',
        'poster' => 'order_processing-fa.jpg',
    ],
];
