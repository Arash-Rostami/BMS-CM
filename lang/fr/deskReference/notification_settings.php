<?php

return [
    'tab_label' => 'Guide : Notifications',

    'tips' => [
        'De quoi s\'agit-il : une alerte d\'événement. Vous dites « prévenez-moi quand un enregistrement de cette table est créé, modifié ou supprimé », et le système vous envoie une alerte.',
        'Exemple : être prévenu à chaque création d\'une facture proforma. Table : Facture Proforma, Actions : Créer. Laissez Colonnes vide.',
        'Exemple : être prévenu quand une expédition atteint un statut. Table : Expédition, Actions : Mettre à jour, Colonnes : Statut, Valeurs des colonnes : le statut attendu.',
        'Exemple : être prévenu quand une demande d\'achat devient urgente. Table : Demande d\'achat, Actions : Mettre à jour, Colonnes : Niveau d\'urgence, Valeurs des colonnes : Élevé.',
        'Une règle se déclenche seulement quand une colonne surveillée prend l\'une des valeurs choisies. Un enregistrement qui a déjà la valeur ne redéclenche pas. Une date choisie se déclenche quand l\'enregistrement arrive à ce jour.',
        'Les changements de tables de liaison ne peuvent pas être surveillés, par exemple rattacher une facture proforma à une demande d\'achat. Surveillez plutôt les colonnes de l\'enregistrement lui-même.',
    ],

    'terms' => [
        ['term' => 'Table', 'definition' => 'Le type d\'enregistrement à surveiller, comme Paiement ou Expédition. Vous pouvez en choisir plusieurs.'],
        ['term' => 'Actions', 'definition' => 'Ce qui arrive à l\'enregistrement : Créer, Mettre à jour ou Supprimer.'],
        ['term' => 'Colonnes', 'definition' => 'Facultatif. Alerte seulement quand ces champs changent. Laissez vide pour être prévenu de tout changement.'],
        ['term' => 'Valeurs des colonnes', 'definition' => 'Facultatif. Alerte seulement quand une colonne surveillée prend l\'une de ces valeurs.'],
        ['term' => 'Utilisateurs', 'definition' => 'Qui reçoit l\'alerte. Vous êtes ajouté par défaut.'],
        ['term' => 'Canal', 'definition' => 'In-app (la cloche d\'alertes), E-mail, ou Les deux.'],
        ['term' => 'Actif', 'definition' => 'Désactivez pour mettre la règle en pause sans la supprimer.'],
        ['term' => 'Qui peut modifier ou supprimer', 'definition' => 'La personne qui a créé la règle, ou toute personne listée comme destinataire. Les autres peuvent seulement consulter.'],
        ['term' => 'Langue et dates', 'definition' => 'Le texte des alertes et des e-mails suit la langue de l\'application. En persan, les dates s\'affichent en calendrier persan.'],
    ],

    'process' => [
        ['title' => 'Appuyez sur Créer', 'description' => 'Le formulaire s\'ouvre.'],
        ['title' => 'Sélectionnez les tables à surveiller', 'description' => 'Choisissez-en une ou plusieurs. Les colonnes et valeurs ci-dessous s\'adaptent.'],
        ['title' => 'Choisissez les actions', 'description' => 'Créer, Mettre à jour, Supprimer, ou un mélange.'],
        ['title' => 'Sélectionnez les colonnes à suivre', 'description' => 'Facultatif, pour les mises à jour. Choisissez les champs qui vous importent.'],
        ['title' => 'Sélectionnez les valeurs des colonnes', 'description' => 'Une liste par colonne. Choisissez dans la liste chargée, ou appuyez sur + pour ajouter une nouvelle valeur.'],
        ['title' => 'Enregistrements liés', 'description' => 'Pour les champs qui pointent vers un autre enregistrement (une société, un service), choisissez-le par son nom, dans l\'une ou l\'autre langue.'],
        ['title' => 'Sélectionnez les utilisateurs et le canal', 'description' => 'Choisissez les destinataires, puis In-app, E-mail ou Les deux.'],
        ['title' => 'Enregistrez', 'description' => 'La règle agit dès la prochaine sauvegarde d\'un enregistrement. Utilisez Actif pour la mettre en pause plus tard.'],
    ],

    'dos' => [
        'Choisissez colonnes et valeurs quand un seul changement vous importe ; vos alertes restent peu nombreuses et utiles.',
        'Prévoyez un événement clair par règle, comme « le statut de l\'expédition devient Arrivée ».',
        'Utilisez le filtre Mes notifications pour retrouver vos règles.',
        'Désactivez une règle au lieu de la supprimer si vous avez seulement besoin d\'une pause.',
        'Ajoutez une courte note pour vous rappeler pourquoi la règle existe.',
    ],

    'donts' => [
        'N\'attendez pas d\'alertes pour les changements de tables de liaison, comme rattacher une facture proforma à une demande d\'achat.',
        'N\'ajoutez pas de personnes qui ne peuvent pas voir le module ; elles sont ignorées et ne reçoivent rien.',
        'Ne répétez pas le même événement dans plusieurs règles ; chaque règle envoie sa propre alerte.',
        'N\'attendez pas d\'alertes lors d\'imports groupés ou de modifications directes de la base de données.',
    ],
];
