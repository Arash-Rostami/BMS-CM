<?php

return [
    'intro' => 'Ce rapport suit chaque commande enregistrée ouverte, de l\'enregistrement jusqu\'au dédouanement : où en est le dossier, depuis combien de jours rien ne s\'y est passé et ce qui a changé récemment. Une commande reste ouverte tant qu\'elle n\'a pas au moins une expédition, qu\'un dossier de douane n\'existe pas pour chaque expédition et que tous ces dossiers de douane n\'ont pas atteint l\'étape de dédouanement (« Terminé », sauf si vous choisissez une autre étape dans le filtre Considérer comme terminé à partir de). Le statut propre de la commande ne la ferme pas. Une commande sans expédition est toujours ouverte. Activez Afficher les dossiers dédouanés pour lister aussi les commandes fermées.',
    'sections' => [
        'filters' => [
            'title' => 'Filtres',
            'intro' => 'Les filtres restreignent ensemble les cartes, le graphique, le tableau et les anomalies de données. Les dernières mises à jour ne suivent que Période d\'activité, Modifié par et Module, et ignorent les autres filtres. Chaque liste affiche toutes les lignes actives, utilisées ou non par une commande. Si la Date de début est postérieure à la Date de fin, les deux sont inversées automatiquement. La barre de filtres est repliée par défaut ; ouvrez-la pour voir les champs. La Période d\'activité, Modifié par, le Module et les champs de seuil se trouvent dans une section Avancé repliée à l\'intérieur ; les autres filtres restent en haut.',
            'items' => [
                'date_from' => [
                    'title' => 'Date de début',
                    'meaning' => 'Masque les commandes dont la date choisie (voir Filtrer les dates selon) est antérieure à ce jour.',
                    'how' => 'Comparée à la date choisie dans Filtrer les dates selon : la Date de Commande par défaut, jamais les dates d\'expédition ou de douane. Les dernières mises à jour l\'ignorent. Une commande sans valeur pour cette date reste visible, sauf si vous désactivez Inclure sans date.',
                    'change' => 'Choisissez un jour, ou videz le champ pour voir toutes les commandes ouvertes.',
                ],
                'date_until' => [
                    'title' => 'Date de fin',
                    'meaning' => 'Masque les commandes dont la date choisie est postérieure à ce jour.',
                    'how' => 'Même règle que la Date de début ; le jour choisi est inclus.',
                    'change' => 'Choisissez un jour, ou videz le champ.',
                ],
                'date_basis' => [
                    'title' => 'Filtrer les dates selon',
                    'meaning' => 'La date de la commande à laquelle les dates de début et de fin sont comparées.',
                    'how' => 'Date de Commande (par défaut), Date de Validité ou jour de création de la commande dans le système. Aucune autre règle ne change.',
                    'change' => 'Choisissez une autre date ; videz le champ pour revenir à la Date de Commande.',
                ],
                'include_undated' => [
                    'title' => 'Inclure sans date',
                    'meaning' => 'Décide du sort des commandes sans valeur pour la date choisie quand une période est définie.',
                    'how' => 'Activé par défaut : ces commandes restent dans les cartes, le tableau et les anomalies, pour que rien ne disparaisse en silence. Désactivé : elles sont masquées car on ne peut pas les placer dans la période. Sans période, ce réglage ne fait rien.',
                    'change' => 'Désactivez-le pour ne voir que les commandes qui ont vraiment une date dans la période.',
                ],
                'seller_id' => [
                    'title' => 'Vendeur',
                    'meaning' => 'Ne montre que les commandes d\'un vendeur.',
                    'how' => 'Correspond au Fournisseur (Vendeur) de la commande. La liste contient toutes les sociétés actives pouvant être vendeur.',
                    'change' => 'Choisissez un autre vendeur ou videz le champ.',
                ],
                'buyer_id' => [
                    'title' => 'Acheteur',
                    'meaning' => 'Ne montre que les commandes d\'un acheteur.',
                    'how' => 'Correspond à l\'Acheteur de la commande. La liste contient toutes les sociétés acheteuses actives.',
                    'change' => 'Choisissez un autre acheteur ou videz le champ.',
                ],
                'company_id' => [
                    'title' => 'Société',
                    'meaning' => 'Ne montre que les commandes dont une société est le vendeur ou l\'acheteur.',
                    'how' => 'Correspond au Vendeur ou à l\'Acheteur de la commande enregistrée : c\'est la version élargie des deux filtres ci-dessus. La liste contient toutes les sociétés actives.',
                    'change' => 'Choisissez une autre société ou videz le champ.',
                ],
                'category_id' => [
                    'title' => 'Catégorie',
                    'meaning' => 'Ne montre que les commandes contenant un produit de cette catégorie ou de l\'une de ses sous-catégories.',
                    'how' => 'Regarde les lignes de la commande. Une seule ligne correspondante suffit ; les autres lignes de la commande restent affichées. La liste montre le chemin complet de chaque catégorie active.',
                    'change' => 'Choisissez une catégorie plus haute pour inclure plus de sous-catégories, ou videz le champ.',
                ],
                'product_id' => [
                    'title' => 'Produit',
                    'meaning' => 'Ne montre que les commandes dont les lignes contiennent ce produit.',
                    'how' => 'Une seule ligne correspondante suffit ; la colonne Produits principaux liste toujours tous les produits de la commande.',
                    'change' => 'Choisissez un autre produit ou videz le champ.',
                ],
                'currency_id' => [
                    'title' => 'Devise',
                    'meaning' => 'Ne montre que les commandes passées dans cette devise.',
                    'how' => 'Correspond à la Devise de la commande enregistrée.',
                    'change' => 'Choisissez une autre devise ou videz le champ.',
                ],
                'registered_order_id' => [
                    'title' => 'Commande enregistrée',
                    'meaning' => 'Ne montre qu\'une seule commande.',
                    'how' => 'La liste contient toutes les commandes, les plus récentes d\'abord, y compris les commandes terminées ; choisir une commande terminée donne des cartes et un tableau vides.',
                    'change' => 'Videz le champ pour revenir à toutes les commandes.',
                ],
                'status_id' => [
                    'title' => 'Statut',
                    'meaning' => 'Ne montre que les commandes dont le statut propre figure parmi ceux choisis. Vous pouvez en choisir plusieurs.',
                    'how' => 'Correspond au Statut de la commande enregistrée (par exemple Brouillon ou Soumis), pas au statut de l\'expédition, de la douane ou du profil bancaire.',
                    'change' => 'Ajoutez ou retirez des statuts, ou videz le champ.',
                ],
                'stage' => [
                    'title' => 'Étape actuelle',
                    'meaning' => 'Ne montre que les commandes à une étape du parcours : Commande Enregistrée, Profil bancaire, Paiement, Expédition ou Douanes et Dédouanement.',
                    'how' => 'Utilise la même règle d\'étape que la carte Dossiers ouverts par étape : cartes, graphiques, tableau et anomalies se restreignent ensemble.',
                    'change' => 'Choisissez une autre étape ou videz le champ.',
                ],
                'show_closed' => [
                    'title' => 'Afficher les dossiers dédouanés',
                    'meaning' => 'Ajoute les commandes fermées au rapport.',
                    'how' => 'Désactivé par défaut : seules les commandes ouvertes sont listées et la première carte indique combien de commandes fermées sont masquées. Activé : les commandes fermées rejoignent cartes, graphiques, tableau et anomalies.',
                    'change' => 'Activez-le pour inclure les commandes fermées.',
                ],
                'cleared_from_stage' => [
                    'title' => 'Considérer comme terminé à partir de',
                    'meaning' => 'L\'étape de douane à partir de laquelle un dossier est dédouané, donc fermé.',
                    'how' => 'Un dossier de douane est dédouané quand son Statut de dédouanement est cette étape ou une étape suivante dans l\'ordre des étapes. Vide : Terminé. Une commande est fermée quand chaque expédition a des dossiers de douane et qu\'ils sont tous dédouanés.',
                    'change' => 'Choisissez une étape plus tôt pour fermer les commandes plus vite, ou videz le champ pour Terminé.',
                ],
                'activity_window' => [
                    'title' => 'Période d\'activité',
                    'meaning' => 'Jusqu\'où les dernières mises à jour remontent dans le temps.',
                    'how' => 'Au choix : 24 dernières heures, 7 ou 30 derniers jours, comptés à partir de maintenant. Par défaut : 7 jours. Seule cette liste l\'utilise ; elle ne modifie pas les jours d\'inactivité.',
                    'change' => 'Choisissez une période plus longue pour voir des changements plus anciens.',
                ],
                'causer_id' => [
                    'title' => 'Modifié par',
                    'meaning' => 'Ne montre dans les dernières mises à jour que les changements d\'un utilisateur.',
                    'how' => 'La liste contient tous les utilisateurs. Seules les dernières mises à jour l\'utilisent. Les changements enregistrés sans utilisateur (affichés « Système ») ne correspondent à aucun utilisateur choisi.',
                    'change' => 'Choisissez un utilisateur, ou videz le champ pour voir tout le monde.',
                ],
                'module' => [
                    'title' => 'Module',
                    'meaning' => 'Ne montre dans les dernières mises à jour que les changements d\'un module.',
                    'how' => 'La liste contient les huit modules couverts : Demande d\'achat, Commande enregistrée, Bon de commande, Paiement, Expédition, Douanes, Profil bancaire et Facture proforma. Un module que vous n\'avez pas le droit de consulter reste vide. Seules les dernières mises à jour l\'utilisent.',
                    'change' => 'Choisissez un autre module ou videz le champ.',
                ],
                'idle_days' => [
                    'title' => 'Seuil de jours d\'inactivité',
                    'meaning' => 'Nombre de jours sans activité à partir duquel un dossier est considéré comme inactif.',
                    'how' => 'Un dossier est inactif quand ses jours d\'inactivité dépassent ce nombre. Vide, il vaut 7 (actuellement :th_idle). Il fixe la première ligne de la carte Dossiers inactifs, la couleur orange de la colonne Jours d\'inactivité, le graphique et l\'anomalie « sans activité ».',
                    'change' => 'Saisissez un nombre entier de jours, 1 ou plus. Un nombre plus élevé classe moins de dossiers comme inactifs.',
                ],
                'th_idle_error' => [
                    'title' => 'Inactivité prolongée (jours)',
                    'meaning' => 'Le nombre de jours d\'inactivité après lequel un dossier est inactif depuis longtemps.',
                    'how' => 'Actuellement :th_idle_error jours ; vide : 14. Il fixe la seconde ligne de la carte Dossiers inactifs, la couleur rouge de la colonne Jours d\'inactivité et l\'anomalie « sans activité depuis longtemps ».',
                    'change' => 'Saisissez un nombre entier de jours entre 1 et 365.',
                ],
                'th_validity' => [
                    'title' => 'Alerte de validité (jours)',
                    'meaning' => 'Combien de jours à l\'avance la validité d\'une commande est considérée comme proche de l\'expiration.',
                    'how' => 'Actuellement :th_validity jours ; vide : 30. Il fixe la ligne Expirent dans … jours de la carte Validité des commandes et l\'anomalie correspondante.',
                    'change' => 'Saisissez un nombre entier de jours entre 1 et 365.',
                ],
                'th_supply' => [
                    'title' => 'Délai du profil (jours)',
                    'meaning' => 'Combien de jours après la Date de Commande une commande sans profil bancaire est considérée en retard.',
                    'how' => 'Actuellement :th_supply jours ; vide : 30. Il fixe l\'anomalie « aucun profil bancaire après longtemps ».',
                    'change' => 'Saisissez un nombre entier de jours entre 1 et 365.',
                ],
            ],
        ],
        'cards' => [
            'title' => 'Cartes',
            'intro' => 'Chaque carte ne compte que les commandes ouvertes qui correspondent aux filtres : les quatre cartes et le tableau décrivent donc toujours le même ensemble de dossiers. Seule la carte des correspondances compte des messages au lieu de commandes. Cliquez sur une ligne des cartes étape, inactivité ou validité (ligne du total comprise) pour filtrer le tableau ci-dessous sur exactement ces dossiers ; une pastille « Tableau filtré par » apparaît au-dessus du tableau avec un bouton « Afficher toutes les lignes », et un nouveau clic sur la même ligne la retire. Les cartes, graphiques, flux des dernières mises à jour et anomalies de données ne changent pas au clic, et l\'export CSV suit le filtre cliqué. La carte des correspondances n\'est pas cliquable : elle compte des messages, pas des dossiers.',
            'items' => [
                'stages' => [
                    'title' => 'Dossiers ouverts par étape',
                    'meaning' => 'Combien de commandes sont ouvertes et jusqu\'où chacune est allée : Commande Enregistrée, Profil bancaire, Paiement, Expédition ou Douanes et Dédouanement.',
                    'how' => 'La première ligne est le total des commandes listées ; les lignes d\'étape s\'additionnent pour donner ce total. Une dernière ligne non cliquable, Commandes clôturées masquées, indique combien de commandes fermées correspondent aux filtres mais sont écartées (elle disparaît quand Afficher les dossiers dédouanés est activé). Chaque commande est comptée une fois, dans l\'étape la plus avancée qui a un enregistrement : un dossier de douane la place dans Douanes et Dédouanement, sinon une expédition dans Expédition, sinon un paiement dans Paiement, sinon un profil bancaire dans Profil bancaire, sinon elle reste dans Commande Enregistrée. Un paiement compte s\'il porte sur la commande elle-même ou sur un bon de commande lié à cette seule commande ; un bon de commande partagé entre plusieurs commandes ne compte pas. Les enregistrements supprimés sont ignorés. L\'étape est le point le plus avancé atteint, pas le statut actuel.',
                    'change' => 'Utilisez les filtres. Pour faire avancer une commande, créez l\'enregistrement de l\'étape suivante (profil bancaire, paiement, expédition, dossier de douane).',
                ],
                'idle' => [
                    'title' => 'Dossiers inactifs',
                    'meaning' => 'Combien de commandes ouvertes n\'ont eu aucune activité depuis longtemps.',
                    'how' => 'Deux lignes. Inactifs (avertissement) compte les dossiers inactifs depuis plus que le Seuil de jours d\'inactivité (actuellement :th_idle jours ; défaut 7). Inactifs (critique) compte les dossiers inactifs depuis plus que le Inactivité prolongée (actuellement :th_idle_error jours ; défaut 14). Un dossier au-delà de la seconde limite figure dans les deux lignes si la première est plus basse. Le calcul des jours est expliqué à la colonne Jours d\'inactivité.',
                    'change' => 'Modifiez le Seuil de jours d\'inactivité ou le Inactivité prolongée. Tout changement enregistré sur un dossier le sort des deux lignes.',
                ],
                'expiring' => [
                    'title' => 'Validité des commandes',
                    'meaning' => 'Combien de commandes ouvertes vont bientôt perdre leur validité, et combien l\'ont déjà perdue.',
                    'how' => 'Expirent dans … jours : Date de Validité entre aujourd\'hui et dans :th_validity jours (filtre Alerte de validité ; défaut 30), les deux jours inclus, et la commande n\'a pas de bon de commande. Déjà expirées : Date de Validité antérieure à aujourd\'hui (aujourd\'hui n\'est pas expiré) et la commande n\'a pas de bon de commande ; le badge de la colonne Date de Validité suit la même règle. Une commande sans Date de Validité n\'est dans aucune de ces lignes ; elle est comptée sur sa propre ligne, Sans date de validité.',
                    'change' => 'Saisissez une Date de Validité plus lointaine sur la commande, ou fermez le dossier en terminant la douane de toutes ses expéditions.',
                ],
                'correspondence' => [
                    'title' => 'Correspondance prioritaire',
                    'meaning' => 'Combien de messages de priorité haute ou urgente restent sans clôture sur des dossiers ouverts.',
                    'how' => 'Compte les correspondances de priorité Haute (première ligne) ou Urgente (seconde ligne) rattachées à une commande ouverte, ou à une expédition ou un dossier de douane d\'une commande ouverte. Un message est clos quand son Statut est Approuvé (Approved) ou Archivé (Archived). Brouillon, Soumis et l\'absence de statut comptent comme ouverts. Les messages supprimés ne sont pas comptés. Comme l\'anomalie de données du même nom, cette carte s\'affiche pour tous les lecteurs du rapport, que vous ayez ou non le droit de consulter les correspondances.',
                    'change' => 'Passez le Statut du message à Approuvé ou Archivé, ou baissez sa priorité. Les filtres de la page la restreignent via la commande.',
                ],
            ],
        ],
        'charts' => [
            'title' => 'Graphiques et mises à jour',
            'items' => [
                'stages' => [
                    'title' => 'Dossiers ouverts par étape',
                    'meaning' => 'Un graphique en anneau avec une part par étape, de Commande Enregistrée à Douanes et Dédouanement.',
                    'how' => 'Même règle d\'étape et mêmes filtres que la carte Dossiers ouverts par étape : chaque part égale la ligne correspondante de la carte.',
                    'change' => 'Utilisez les filtres de la page.',
                ],
                'idle_by_stage' => [
                    'title' => 'Où les dossiers sont inactifs',
                    'meaning' => 'Une barre empilée par étape qui sépare les dossiers ouverts de l\'étape en dossiers actifs et inactifs, pour voir à quelle étape des dossiers restent sans que personne n\'y touche.',
                    'how' => 'Elle utilise les mêmes dossiers ouverts, la même règle d\'étape et les mêmes filtres que le graphique des dossiers ouverts par étape, et la même règle de dernière activité que la carte des dossiers inactifs : un dossier est inactif lorsque plus de jours que le Seuil de jours d\'inactivité se sont écoulés depuis son dernier changement enregistré (ou depuis sa dernière modification s\'il n\'a aucun changement enregistré). Les deux parties d\'une barre totalisent la part de cette étape dans le graphique par étape, et les parties inactives totalisent la première ligne de la carte des dossiers inactifs.',
                    'change' => 'Modifiez le Seuil de jours d\'inactivité pour déplacer la limite entre actif et inactif ; utilisez les autres filtres pour restreindre les dossiers.',
                ],
                'activity_feed' => [
                    'title' => 'Dernières mises à jour',
                    'meaning' => 'La liste des changements récents, du plus récent au plus ancien, avec la date, l\'auteur, le dossier, le module et le changement.',
                    'how' => 'Chaque ligne est une modification enregistrée ou un changement de statut. Une modification montre les champs changés sous la forme ancienne valeur → nouvelle valeur ; un changement de statut montre le champ et l\'ancien et le nouveau statut. Seuls les changements de la Période d\'activité sont listés (7 derniers jours sauf changement). Sont couverts : Demande d\'achat, Commande enregistrée, Bon de commande, Paiement, Expédition, Douanes, Profil bancaire et Facture proforma, et seulement les modules que vous avez le droit de consulter. La colonne Dossier affiche le numéro de la commande enregistrée ; pour les modules qui ne sont pas rattachés à une commande (demandes d\'achat, bons de commande, paiements, factures proforma), elle affiche un tiret. Par indique l\'utilisateur ; Système signifie que le changement a été enregistré sans utilisateur. La liste n\'est pas limitée aux commandes ouvertes et ignore Vendeur, Acheteur, Catégorie, Produit, Devise, Commande enregistrée, Statut, les dates et les Jours d\'inactivité. Pages de 10, 25 ou 50 lignes.',
                    'change' => 'Modifiez la Période d\'activité, Modifié par ou Module. Pour une commande précise, regardez la colonne Dossier ; les autres filtres n\'atteignent pas cette liste.',
                ],
            ],
        ],
        'table' => [
            'title' => 'Tableau',
            'intro' => 'Une ligne par commande ouverte correspondant aux filtres. Le bouton Groupe du tableau regroupe les lignes par Vendeur ou par Étape actuelle. Tri par défaut sur Jours d\'inactivité, du plus grand au plus petit, avec 25 lignes par page (10, 25, 50 ou 100 possibles). La recherche porte sur le numéro de commande, le numéro de contrat, le vendeur et le statut. Le menu des colonnes permet d\'afficher ou de masquer des colonnes ; N° de Contrat, Acheteur, Devise, Date de Commande, Statut de l\'opération, Statut des documents, Dernière mise à jour par et Dernière correspondance sont masquées au départ. La recherche porte aussi sur les noms et codes de produits. Les colonnes calculées à partir des derniers enregistrements (statuts, Dernière opération, Dernière mise à jour par) ne peuvent être ni recherchées ni triées. Les colonnes de statut viennent de la dernière expédition (plus grand numéro d\'enregistrement) et du dernier dossier de douane de la commande.',
            'items' => [
                'ro_number' => [
                    'title' => 'Numéro du bon de commande',
                    'meaning' => 'Le numéro de la commande enregistrée. Survolez-le pour voir la Date de Commande.',
                ],
                'contract_no' => [
                    'title' => 'N° de Contrat',
                    'meaning' => 'Le numéro de contrat de la commande. Masqué au départ.',
                ],
                'sellerCompany.name' => [
                    'title' => 'Fournisseur',
                    'meaning' => 'Le fournisseur de la commande.',
                ],
                'products' => [
                    'title' => 'Produits principaux',
                    'meaning' => 'Les produits distincts des lignes de la commande.',
                    'how' => 'Le tableau en montre trois au maximum ; l\'export CSV les liste tous.',
                ],
                'stage' => [
                    'title' => 'Étape actuelle',
                    'meaning' => 'L\'étape la plus avancée atteinte par la commande.',
                    'how' => 'Même règle que la carte Dossiers ouverts par étape. Le tri de cette colonne ne suit pas l\'ordre des étapes.',
                ],
                'status.name' => [
                    'title' => 'Statut',
                    'meaning' => 'Le statut propre de la commande. Il ne décide pas si la commande est ouverte.',
                ],
                'buyerCompany.name' => [
                    'title' => 'Acheteur',
                    'meaning' => 'L\'acheteur de la commande. Masqué au départ.',
                ],
                'currency.name' => [
                    'title' => 'Devise',
                    'meaning' => 'La devise de la commande. Masquée au départ.',
                ],
                'order_date' => [
                    'title' => 'Date de Commande',
                    'meaning' => 'La date de la commande. Masquée au départ.',
                ],
                'purchase_orders_count' => [
                    'title' => 'Bons de commande',
                    'meaning' => 'Combien de bons de commande ont été créés à partir de cette commande.',
                    'how' => 'Les bons de commande supprimés ne comptent pas. C\'est un bon de commande qui fait disparaître le badge Expirée de la Date de Validité.',
                ],
                'validity_date' => [
                    'title' => 'Date de Validité',
                    'meaning' => 'La date jusqu\'à laquelle la commande est valable.',
                    'how' => 'La cellule affiche un badge rouge Expirée à la place de la date quand la Date de Validité est antérieure à aujourd\'hui (aujourd\'hui n\'est pas expiré) et que la commande n\'a pas de bon de commande. Avec un bon de commande, la date simple reste affichée. La ligne Déjà expirées et l\'anomalie suivent la même règle.',
                    'change' => 'Modifiez la Date de Validité dans l\'onglet Général de la commande.',
                ],
                'fx_status' => [
                    'title' => 'Statut des devises',
                    'meaning' => 'Où en est l\'approvisionnement en devises.',
                    'how' => 'Prend le statut de tous les profils bancaires de la commande et affiche le plus avancé dans l\'ordre des statuts de profil bancaire. Vide si la commande n\'a pas de profil bancaire.',
                ],
                'shipment_status' => [
                    'title' => 'Statut de suivi',
                    'meaning' => 'Le Statut de Suivi de la dernière expédition.',
                    'how' => 'Seule la dernière expédition est utilisée ; les précédentes de la même commande ne sont pas montrées. Vide s\'il n\'y a pas d\'expédition ou pas de statut.',
                ],
                'operation_status' => [
                    'title' => 'Statut de l\'opération',
                    'meaning' => 'Le statut de l\'opération de la dernière expédition. Masqué au départ.',
                ],
                'doc_status' => [
                    'title' => 'Statut des documents',
                    'meaning' => 'Le statut des documents de la dernière expédition. Masqué au départ.',
                ],
                'clearance_status' => [
                    'title' => 'Statut de dédouanement',
                    'meaning' => 'Le Statut de Dédouanement du dernier dossier de douane de la commande.',
                    'how' => 'Même si la commande a plusieurs dossiers de douane, seul le dernier est affiché.',
                ],
                'last_operation' => [
                    'title' => 'Dernière opération',
                    'meaning' => 'Ce qui s\'est passé lors de la dernière activité enregistrée du dossier.',
                    'how' => 'Pour une modification : les noms d\'au plus trois champs changés, avec le nombre des autres (+2). Pour un changement de statut : le champ avec ancien statut → nouveau statut. Un tiret s\'affiche si la dernière activité relève d\'un module que vous n\'avez pas le droit de consulter, ou si aucune activité n\'est enregistrée ; même règle que pour les dernières mises à jour.',
                ],
                'last_activity_at' => [
                    'title' => 'Dernière activité',
                    'meaning' => 'La date et l\'heure de la dernière activité enregistrée.',
                    'how' => 'La modification ou le changement de statut enregistré le plus récent sur la commande, ou sur ses profils bancaires, expéditions ou dossiers de douane. Les paiements, la correspondance et les lignes de commande ne comptent pas. Un tiret signifie que rien n\'est enregistré ; les jours d\'inactivité sont alors comptés depuis la dernière mise à jour de la commande. C\'est le cas des enregistrements sans historique.',
                ],
                'last_by' => [
                    'title' => 'Dernière mise à jour par',
                    'meaning' => 'L\'utilisateur qui a fait la dernière activité enregistrée. Masqué au départ.',
                    'how' => 'Un tiret si aucun utilisateur n\'est enregistré.',
                ],
                'idle_days' => [
                    'title' => 'Jours d\'inactivité',
                    'meaning' => 'Nombre de jours depuis la dernière fois que quelque chose a été enregistré sur le dossier.',
                    'how' => 'Aujourd\'hui moins la date de Dernière activité. Sans activité enregistrée, le compte part de la dernière mise à jour de la commande, ou de sa création si elle n\'a jamais été mise à jour ; une info-bulle le signale alors sur le nombre. Couleur : rouge au-delà de :th_idle_error jours (Inactivité prolongée), orange au-delà de :th_idle jours (Seuil de jours d\'inactivité), gris sinon.',
                    'change' => 'Enregistrez un changement sur la commande, le profil bancaire, l\'expédition ou le dossier de douane pour le remettre à zéro. Modifiez les deux seuils pour déplacer les limites de couleur.',
                ],
                'last_correspondence' => [
                    'title' => 'Dernière correspondance',
                    'meaning' => 'Le sujet du message le plus récent rattaché directement à la commande, avec ses destinataires « À » en dessous. Masquée au départ.',
                    'how' => 'Les messages rattachés aux expéditions ou aux dossiers de douane ne sont pas pris en compte ici. Le sujet est coupé à 40 caractères.',
                ],
                'export_csv' => [
                    'title' => 'Export CSV',
                    'meaning' => 'Télécharge le rapport sous forme de fichier CSV.',
                    'how' => 'Le fichier est préparé en arrière-plan ; une notification avec un lien de téléchargement apparaît quand il est prêt. Il contient toutes les commandes ouvertes correspondant aux filtres actuels, pas seulement la page affichée, avec toutes les colonnes, même masquées. La recherche et le tri du tableau ne sont pas appliqués.',
                    'change' => 'Réglez d\'abord les filtres, puis cliquez sur le bouton.',
                ],
            ],
        ],
        'issues' => [
            'title' => 'Anomalies de données',
            'intro' => 'La section en bas de page liste les enregistrements qui semblent erronés ou qui demandent une action. Elle n\'apparaît que s\'il existe au moins une anomalie. Les erreurs sont rouges et les avertissements orange ; ce rapport n\'a pas de contrôle de niveau information. Les pastilles indiquent le nombre d\'anomalies par niveau (masquées à zéro) ; un clic filtre la liste sur ce niveau et « Tout afficher » retire ce filtre. La liste peut aussi être filtrée par gravité et par contrôle, affiche les erreurs en premier et donne un lien vers l\'enregistrement si vous avez le droit de le modifier. Elle suit les filtres de la page, sauf Période d\'activité, Modifié par et Module. Elle ne contient que les contrôles des modules que vous pouvez consulter (Commande enregistrée, Expédition) ; le contrôle d\'un module non autorisé n\'est ni compté ni listé. Le contrôle des correspondances s\'affiche pour tous les lecteurs. Les résultats sont actualisés environ toutes les 5 minutes, et immédiatement quand un enregistrement est sauvegardé. Une anomalie n\'empêche jamais d\'enregistrer. Sauf mention contraire, un contrôle regarde les commandes listées : les ouvertes, ou toutes quand Afficher les dossiers dédouanés est activé. « Modifié par » et « Modifié le » indiquent qui a modifié en dernier l\'enregistrement, ou le champ de statut contrôlé.',
            'items' => [
                'registered_order_idle_over_threshold' => [
                    'title' => 'Sans activité au-delà du seuil',
                    'meaning' => 'Avertissement. Un dossier ouvert n\'a eu aucune activité enregistrée depuis plus de jours que le Seuil de jours d\'inactivité.',
                    'how' => 'Les jours d\'inactivité (colonne du tableau) dépassent le Seuil de jours d\'inactivité (actuellement :th_idle ; défaut 7). La valeur actuelle indique les jours, avec une mention quand ils sont comptés depuis la dernière mise à jour faute d\'historique.',
                    'change' => 'Enregistrez une avancée sur la commande, le profil bancaire, l\'expédition ou le dossier de douane, par exemple en mettant à jour le Statut dans l\'onglet Général de la commande. Ou augmentez le Seuil de jours d\'inactivité.',
                ],
                'registered_order_idle_over_14' => [
                    'title' => 'Sans activité depuis longtemps',
                    'meaning' => 'Erreur. Un dossier ouvert n\'a eu aucune activité enregistrée depuis plus que le Inactivité prolongée.',
                    'how' => 'Même calcul que ci-dessus avec le Inactivité prolongée (actuellement :th_idle_error jours ; défaut 14). Si le Seuil de jours d\'inactivité est plus bas, un dossier au-delà de cette limite figure aussi dans le contrôle du seuil.',
                    'change' => 'Enregistrez une avancée sur le dossier, comme ci-dessus, ou modifiez le Inactivité prolongée.',
                ],
                'registered_order_validity_expiring' => [
                    'title' => 'La validité de la commande se termine bientôt',
                    'meaning' => 'Avertissement. La Date de Validité de la commande tombe dans la Alerte de validité.',
                    'how' => 'Date de Validité entre aujourd\'hui et dans :th_validity jours (défaut 30), les deux inclus, et la commande n\'a pas de bon de commande.',
                    'change' => 'Si la commande a été renouvelée, saisissez la nouvelle Date de Validité dans l\'onglet Général. Ou fermez le dossier en terminant la douane de toutes ses expéditions.',
                ],
                'registered_order_validity_expired' => [
                    'title' => 'La validité de la commande est dépassée',
                    'meaning' => 'Erreur. La Date de Validité est passée et le dossier est toujours ouvert.',
                    'how' => 'Date de Validité antérieure à aujourd\'hui (aujourd\'hui n\'est pas expiré) et la commande n\'a pas de bon de commande ; le badge de la colonne Date de Validité suit la même règle. Une commande sans Date de Validité n\'est pas signalée.',
                    'change' => 'Saisissez une Date de Validité plus lointaine dans l\'onglet Général de la commande, ou terminez la douane de toutes ses expéditions.',
                ],
                'registered_order_no_items' => [
                    'title' => 'Aucune ligne de commande',
                    'meaning' => 'Erreur. La commande n\'a aucune ligne, ses produits sont donc inconnus.',
                    'how' => 'La commande n\'a aucune ligne ; les lignes supprimées ne comptent pas.',
                    'change' => 'Ajoutez au moins une ligne dans la section Lignes de commande de l\'onglet Général de la commande.',
                ],
                'registered_order_no_bank_profile_after_n_days' => [
                    'title' => 'Toujours pas de profil bancaire après longtemps',
                    'meaning' => 'Avertissement. L\'approvisionnement en devises n\'a pas été lancé pour la commande.',
                    'how' => 'La commande n\'a pas de profil bancaire et sa Date de Commande remonte à plus de :th_supply jours (défaut 30).',
                    'change' => 'Créez un profil bancaire pour la commande. Ne corrigez la Date de Commande que si elle a été mal saisie.',
                ],
                'registered_order_missing_validity_date' => [
                    'title' => 'Pas de date de validité',
                    'meaning' => 'Avertissement. La commande n\'a pas de Date de Validité : elle ne peut jamais apparaître comme bientôt expirée ou expirée.',
                    'how' => 'Le champ Date de Validité d\'une commande listée est vide. Ces mêmes commandes sont comptées sur la ligne Sans date de validité de la carte Validité des commandes.',
                    'change' => 'Saisissez la Date de Validité dans l\'onglet Général de la commande.',
                ],
                'registered_order_missing_party' => [
                    'title' => 'Vendeur ou acheteur manquant',
                    'meaning' => 'Erreur. La commande n\'a pas de vendeur ou pas d\'acheteur.',
                    'how' => 'Le Vendeur ou l\'Acheteur d\'une commande listée est vide ou pointe vers une société supprimée. La valeur actuelle nomme la partie manquante.',
                    'change' => 'Choisissez la société manquante dans l\'onglet Général de la commande.',
                ],
                'registered_order_missing_currency' => [
                    'title' => 'Devise manquante',
                    'meaning' => 'Erreur. La commande n\'a pas de devise : ses montants ne peuvent pas être lus correctement.',
                    'how' => 'La Devise d\'une commande listée est vide ou pointe vers une devise supprimée.',
                    'change' => 'Choisissez la Devise dans l\'onglet Général de la commande.',
                ],
                'registered_order_in_customs_without_payment_or_bank_profile' => [
                    'title' => 'Expédiée ou en douane sans paiement ni profil bancaire',
                    'meaning' => 'Avertissement. La commande est arrivée à l\'expédition ou à la douane, mais rien n\'indique comment elle a été payée ni comment les devises ont été fournies.',
                    'how' => 'Une commande listée a une expédition ou un dossier de douane, aucun profil bancaire et aucun paiement (sur la commande elle-même ou sur un bon de commande lié à cette seule commande). Les enregistrements supprimés sont ignorés.',
                    'change' => 'Enregistrez le paiement ou créez le profil bancaire de la commande.',
                ],
                'shipment_missing_tracking_status' => [
                    'title' => 'Expédition sans statut de suivi',
                    'meaning' => 'Avertissement. Une expédition semble en cours, mais son statut de suivi est vide.',
                    'how' => 'Une expédition d\'une commande ouverte n\'a pas de Statut de Suivi alors qu\'une Date de Sortie ou un ETD est renseigné.',
                    'change' => 'Choisissez le Statut de Suivi dans l\'onglet Logistique & Détails de l\'expédition.',
                ],
                'correspondence_open_high_priority' => [
                    'title' => 'Correspondance prioritaire encore ouverte',
                    'meaning' => 'Avertissement. Un message de priorité haute ou urgente sur un dossier ouvert n\'est pas clos.',
                    'how' => 'La priorité est Haute ou Urgente et le Statut du message n\'est ni Approuvé ni Archivé, sur une commande ouverte ou sur ses expéditions ou dossiers de douane. La valeur actuelle indique la priorité.',
                    'change' => 'Répondez au message et passez son Statut à Approuvé ou Archivé dans l\'onglet Détails du message, ou baissez sa priorité.',
                ],
            ],
        ],
    ],
];
