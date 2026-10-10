<?php

return [
    'tab_label' => 'Guide : Règles du calendrier',

    'tips' => [
        'De quoi s\'agit-il : un rappel basé sur une date. Une règle surveille une date des enregistrements d\'un module, affiche chacun dans le calendrier du tableau de bord et envoie des alertes avant cette date et le jour même.',
        'Exemple : paiement bientôt dû. Module : Paiement, Colonne de date : dernier jour pour payer, Délais d\'alerte : 7, 3, 1.',
        'Exemple : relancer une demande d\'achat 2 jours après son approbation. Module : Demande d\'achat, Colonne de date : date d\'approbation, Décalage : +2, aucun délai d\'alerte, Alerter aussi le jour même : activé.',
        'Exemple : l\'offre d\'une facture proforma va expirer. Module : Facture Proforma, Colonne de date : expiration de l\'offre, délais d\'alerte au choix.',
        'Exemple : arrivée des marchandises. Module : Expédition, Colonne de date : arrivée estimée (ETA), Délais d\'alerte : 3, 1.',
        'Timing : une nouvelle règle apparaît en quelques secondes ; les modifications après environ 2 minutes ; tout est reconstruit chaque jour à 02:00 ; les alertes partent à 07:00. Un worker de file d\'attente doit tourner.',
        'Limite : un changement qui ne touche qu\'un lien entre enregistrements, ou une écriture directe en base, apparaît à la prochaine reconstruction de 02:00.',
    ],

    'terms' => [
        ['term' => 'Module', 'definition' => 'Les enregistrements surveillés par la règle, comme Paiement ou Expédition. Les dates et conditions en dépendent.'],
        ['term' => 'Colonne de date', 'definition' => 'La date à partir de laquelle la règle compte. Chaque option est accompagnée d\'une courte explication.'],
        ['term' => 'Décalage (jours)', 'definition' => 'Déplace la date de quelques jours. +2 signifie deux jours après, -1 un jour avant.'],
        ['term' => 'Délais d\'alerte (jours)', 'definition' => 'Combien de jours avant la date vous voulez une alerte, par exemple 7, 3, 1. Tout nombre de 0 à 120.'],
        ['term' => 'Alerter aussi le jour même', 'definition' => 'Envoie une alerte de plus à la date.'],
        ['term' => 'Rappel', 'definition' => 'Indicatif. Il quitte « À traiter » une fois la date passée.'],
        ['term' => 'Action', 'definition' => 'Reste affiché, avec des alertes de retard, jusqu\'au traitement.'],
        ['term' => 'Visibilité', 'definition' => 'Qui voit la règle et reçoit ses alertes : Moi uniquement, Tout le monde, Utilisateurs spécifiques ou Selon les rôles.'],
        ['term' => 'Partagé avec', 'definition' => 'Apparaît pour Utilisateurs spécifiques. Seules les personnes pouvant voir le module peuvent être choisies.'],
        ['term' => 'Rôles', 'definition' => 'Apparaît pour Selon les rôles. Les membres qui peuvent aussi voir le module voient la règle et reçoivent ses alertes.'],
        ['term' => 'Alerter aussi ces e-mails', 'definition' => 'Pour les personnes sans compte, jusqu\'à 10 adresses, par e-mail uniquement. Elles voient les noms ou numéros des enregistrements de l\'alerte.'],
        ['term' => 'Canal de notification', 'definition' => 'In-app, E-mail ou Les deux. Les e-mails externes demandent E-mail ou Les deux.'],
        ['term' => 'Onglet Conditions', 'definition' => 'Précise quels enregistrements comptent, par exemple un seul service ou certains statuts. Laissez vide pour tout inclure.'],
        ['term' => 'Afficher les enregistrements correspondants', 'definition' => 'Bouton d\'aperçu qui indique combien d\'enregistrements correspondent et en montre quelques-uns.'],
    ],

    'process' => [
        ['title' => 'Appuyez sur Créer', 'description' => 'Le formulaire s\'ouvre sur l\'onglet Règle et partage.'],
        ['title' => 'Nom, Type et Canal', 'description' => 'Donnez un nom court, choisissez Rappel ou Action, puis le canal.'],
        ['title' => 'Module et Colonne de date', 'description' => 'Choisissez d\'abord le module ; la liste des colonnes de date se remplit ensuite, groupée par module.'],
        ['title' => 'Décalage, Délais d\'alerte, jour même', 'description' => 'Réglez le décalage si le rappel n\'est pas à la date même, ajoutez les jours d\'avance et activez l\'alerte du jour si vous la voulez.'],
        ['title' => 'Visibilité', 'description' => 'Choisissez qui voit la règle. Ajoutez utilisateurs ou rôles quand le choix le demande, et des e-mails externes si besoin.'],
        ['title' => 'Couleur', 'description' => 'Choisissez une couleur pour distinguer les règles dans le calendrier.'],
        ['title' => 'Onglet Conditions', 'description' => 'Dans la case Table, choisissez le module lui-même, une table liée directement ou une table atteinte par elle. Ajoutez les conditions depuis les listes. Pour « ceci OU cela », utilisez le bouton Ajouter une série alternative (OU).'],
        ['title' => 'Aperçu et enregistrement', 'description' => 'Appuyez sur Afficher les enregistrements correspondants pour vérifier le nombre, puis enregistrez. Utilisez Actif pour mettre la règle en pause plus tard.'],
    ],

    'dos' => [
        'Une règle par objectif, avec un nom clair comme « Arrivée de l\'expédition ».',
        'Utilisez Action pour ce que quelqu\'un doit faire, et Rappel pour ce qu\'il est seulement bon de savoir.',
        'Regardez l\'aperçu avant d\'enregistrer pour vérifier que la règle cible bien ce que vous attendez.',
        'Choisissez des rôles quand toute une équipe doit voir la règle ; les membres ont besoin de la permission de voir le module.',
        'Utilisez Dupliquer pour créer rapidement une variante d\'une règle existante.',
        'Utilisez le bouton Activité du calendrier pour voir pourquoi un enregistrement est apparu ou non.',
    ],

    'donts' => [
        'N\'attendez pas d\'effet immédiat des modifications ; une règle existante modifiée se met à jour après environ 2 minutes.',
        'Ne créez pas de quasi-copies d\'une règle ; si une règle similaire existe, fusionnez-y vos délais d\'alerte.',
        'N\'ajoutez pas d\'e-mails externes avec le canal In-app ; ils ne reçoivent que des e-mails.',
        'N\'attendez pas plus d\'un message par règle et par personne et par jour ; il regroupe tous les éléments à échéance.',
        'Ne laissez pas active une règle devenue inutile ; désactivez-la.',
    ],
];
