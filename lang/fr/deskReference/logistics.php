<?php

return [
    'tab_label' => 'Guide de Référence : Logistique et Dédouanement',
    'scope_label' => 'Étape d\'expédition, de douane et de dédouanement',
    'covers' => ['shipment', 'custom'],

    'tips' => [
        'La valeur en douane en Iran est calculée sur une base CIF — déclarez le transport et l\'assurance à la douane même sur les transactions FOB ou EXW.',
    ],

    'terms' => [
        ['term' => 'Fret maritime', 'definition' => 'L\'option la moins chère pour de grands volumes ; transit plus long. FCL = conteneur complet / LCL = groupage (moins d\'un conteneur).'],
        ['term' => 'Fret aérien', 'definition' => 'Rapide, coût plus élevé ; adapté aux marchandises de faible volume, urgentes ou périssables.'],
        ['term' => 'Fret routier', 'definition' => 'Adapté aux pays voisins (Turquie, Irak, États d\'Asie centrale).'],
        ['term' => 'Fret ferroviaire', 'definition' => 'Une option intermédiaire ; itinéraires tels que Chine-Iran via le Kazakhstan/Turkménistan.'],
        ['term' => 'THC', 'definition' => 'Frais de Manutention au Terminal (Terminal Handling Charge).'],
        ['term' => 'Surestaries/Détention', 'definition' => 'Frais de retard de déchargement / retard de restitution de conteneur.'],
        ['term' => 'EXW', 'definition' => 'Livraison à la sortie de l\'usine du vendeur ; toute la responsabilité du transport est transférée à l\'acheteur à partir de ce point.'],
        ['term' => 'FCA', 'definition' => 'Le vendeur dédouane les marchandises à l\'exportation et les remet au transporteur de l\'acheteur.'],
        ['term' => 'FOB', 'definition' => 'Le vendeur est responsable de la livraison des marchandises à bord du navire au port d\'origine ; l\'incoterm le plus courant dans les importations maritimes de l\'Iran.'],
        ['term' => 'CFR', 'definition' => 'Identique au FOB, avec en plus le paiement du transport jusqu\'au port de destination (sans assurance).'],
        ['term' => 'CIF', 'definition' => 'Identique au CFR, avec en plus l\'assurance du transport ; l\'incoterm le plus courant dans les factures proforma d\'enregistrement de commande car il sert de base à l\'évaluation douanière en Iran.'],
        ['term' => 'DAP/DDP', 'definition' => 'Livraison à destination avec ou sans paiement des droits d\'importation ; utilisation plus limitée dans les importations de l\'Iran.'],
        ['term' => 'Déclaration en douane', 'definition' => 'Dépôt officiel basé sur le connaissement, la facture finale, la liste de colisage, le certificat d\'origine et le numéro d\'enregistrement de commande.'],
        ['term' => 'Valeur en douane', 'definition' => 'La valeur CIF des marchandises ; base de calcul des droits d\'importation.'],
        ['term' => 'Droits d\'importation', 'definition' => 'Droits de douane + bénéfice commercial + autres prélèvements légaux.'],
        ['term' => 'Circuit vert/jaune/rouge', 'definition' => 'Évaluation des risques douaniers : vert (pas d\'inspection physique), jaune (contrôle documentaire), rouge (inspection physique complète).'],
        ['term' => 'Permis de douane (feuillet vert)', 'definition' => 'Autorisation de libérer les marchandises de l\'entrepôt sous douane après règlement complet.'],
        ['term' => 'Levée de l\'engagement de change', 'definition' => 'Soumission du permis de douane à la banque agente dans le délai légal.'],
    ],

    'process' => [
        ['title' => 'Cotation de fret', 'description' => 'Fournir au transitaire les spécifications de l\'expédition : poids, volume (CBM), type d\'emballage, port/aéroport d\'origine et de destination.'],
        ['title' => 'Comparer les devis des transitaires', 'description' => 'Taux de fret, temps de transit, conditions de paiement et coûts accessoires (THC, surestaries/détention, assurance).'],
        ['title' => 'Choisir l\'Incoterm', 'description' => 'Et l\'inscrire sur la proforma/le bon de commande (PO).'],
        ['title' => 'Déposer la déclaration en douane', 'description' => 'Sur la base du connaissement, de la facture finale, de la liste de colisage, du certificat d\'origine et du numéro d\'enregistrement de commande.'],
        ['title' => 'Déterminer la valeur en douane et le code tarifaire final', 'description' => 'Sur une base CIF, par l\'expert en douane.'],
        ['title' => 'Calculer et payer les droits d\'importation', 'description' => 'Droits de douane + bénéfice commercial + autres prélèvements légaux.'],
        ['title' => 'Attribution du circuit de risque', 'description' => 'Par le système de gestion des risques douaniers (vert/jaune/rouge).'],
        ['title' => 'Obtenir les approbations techniques', 'description' => 'Normes, santé, etc. à la douane, si requis.'],
        ['title' => 'Délivrer le permis de douane (feuillet vert)', 'description' => 'Après règlement complet.'],
        ['title' => 'Mainlevée physique des marchandises', 'description' => 'Depuis l\'entrepôt sous douane.'],
        ['title' => 'Lever l\'engagement de change', 'description' => 'Soumettre le permis de douane à la banque agente dans le délai légal.'],
    ],

    'dos' => [
        'Déclarer les coûts de transport et d\'assurance à la douane même sur les transactions FOB ou EXW — la valeur en douane de l\'Iran est calculée sur une base CIF.',
        'Obtenir des devis de plusieurs transitaires et comparer le taux de fret, le temps de transit et les conditions de paiement.',
        'Afficher les statuts "délai restant de l\'engagement de change" et "documents envoyés à la banque" pour chaque dossier.',
        'Soumettre le permis de douane avec des spécifications quantitatives/qualitatives correspondant à l\'enregistrement de la commande et aux documents de change.',
    ],

    'donts' => [
        'Ignorer les coûts accessoires (THC, surestaries/détention, assurance) lors de la comparaison des taux de fret.',
        'Omettre de lever l\'engagement de change à temps — suspension du renouvellement de la carte commerciale, amendes ou action de la banque centrale.',
    ],

    'as_of' => 'Juillet 2026',

    'media' => [
        'video' => 'logistics-fa.mp4',
        'audio' => 'logistics-fa.m4a',
        'poster' => 'logistics-fa.jpg',
    ],
];
