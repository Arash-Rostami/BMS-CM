<?php

return [
    'intro' => 'Ce rapport suit chaque profil bancaire dans la file d\'approvisionnement en devises : en attente d\'allocation, alloué, acheté et reçu. Seuls les profils non supprimés sont comptés. Les montants de devises différentes ne sont jamais additionnés : chaque devise a sa propre ligne, et le total en euros est une conversion à part.',
    'sections' => [
        'filters' => [
            'title' => 'Filtres',
            'intro' => 'La barre de filtres est repliée par défaut ; ouvrez-la pour voir les champs. Chaque filtre restreint toute la page d\'un coup : les quatre cartes, les deux graphiques, le tableau, les anomalies de données et leurs compteurs, ainsi que l\'export CSV. Les dates portent sur la date choisie dans Filtrer les dates selon (la Date de création du profil bancaire par défaut). Un champ vide n\'applique aucune limite. Si la Date de début est postérieure à la Date de fin, les deux sont échangées automatiquement. Les listes Banque, Société et Devise ne montrent que les éléments actifs ; Commande enregistrée et Statut listent tous les éléments ; Source d\'approvisionnement est une liste fixe. La seule chose que les filtres ne touchent pas est le taux de change vers l\'euro derrière les totaux en euros : il provient toujours de l\'ensemble des profils achetés.',
            'items' => [
                'date_from' => [
                    'title' => 'Date de début',
                    'meaning' => 'Masque les profils dont la date choisie est avant ce jour.',
                    'how' => 'Comparée à la date choisie dans Filtrer les dates selon (Date de création par défaut). Le jour choisi est inclus. Un profil sans valeur pour cette date reste visible, sauf si vous désactivez Inclure sans date.',
                    'change' => 'Choisissez un jour plus ancien pour inclure d\'anciens profils, ou videz le champ pour supprimer la limite. Les dates suivent le calendrier choisi dans la barre du haut.',
                ],
                'date_until' => [
                    'title' => 'Date de fin',
                    'meaning' => 'Masque les profils dont la date choisie est après ce jour.',
                    'how' => 'Même date et même règle que la Date de début. Le jour choisi est inclus.',
                    'change' => 'Choisissez un jour plus récent pour inclure les profils récents, ou videz le champ pour supprimer la limite.',
                ],
                'date_basis' => [
                    'title' => 'Filtrer les dates selon',
                    'meaning' => 'La date du profil bancaire à laquelle les dates de début et de fin sont comparées.',
                    'how' => 'Date de création (par défaut), Date d\'allocation, Date d\'achat ou Date d\'échéance de paiement. S\'applique de la même façon aux cartes, graphiques, tableau et anomalies. L\'exception est la carte Achetés sur la période sélectionnée : quand une période est définie, elle utilise toujours la Date d\'achat, quel que soit votre choix ici, tout comme ses lignes cliquables.',
                    'change' => 'Choisissez une autre date ; videz le champ pour revenir à la Date de création.',
                ],
                'include_undated' => [
                    'title' => 'Inclure sans date',
                    'meaning' => 'Décide du sort des profils sans valeur pour la date choisie quand une période est définie.',
                    'how' => 'Activé par défaut : ces profils restent dans les cartes, le tableau et les anomalies, pour que rien ne disparaisse en silence (par exemple un profil acheté sans Date d\'achat). Désactivé : ils sont masqués. Sans période, ce réglage ne fait rien. La carte Achetés sur la période sélectionnée fait exception : elle ne compte jamais un profil sans Date d\'achat, quel que soit ce réglage.',
                    'change' => 'Désactivez-le pour ne voir que les profils qui ont vraiment une date dans la période.',
                ],
                'bank_id' => [
                    'title' => 'Banque (agent)',
                    'meaning' => 'N\'affiche que les profils traités par une banque.',
                    'how' => 'Correspond à la Banque (agent) indiquée sur chaque profil. Dès qu\'une banque est choisie, les profils sans banque sont masqués.',
                    'change' => 'Choisissez une autre banque, ou videz le champ pour voir toutes les banques.',
                ],
                'company_id' => [
                    'title' => 'Société',
                    'meaning' => 'N\'affiche que les profils d\'une société.',
                    'how' => 'Correspond à la Société indiquée sur chaque profil bancaire, et non au vendeur ou à l\'acheteur de la commande.',
                    'change' => 'Choisissez une autre société, ou videz le champ.',
                ],
                'registered_order_id' => [
                    'title' => 'Commande enregistrée',
                    'meaning' => 'N\'affiche que les profils liés à une commande enregistrée.',
                    'how' => 'Correspond à la Commande enregistrée de chaque profil. La liste contient toutes les commandes enregistrées, des plus récentes aux plus anciennes, pas seulement les actives.',
                    'change' => 'Choisissez une autre commande, ou videz le champ.',
                ],
                'currency_id' => [
                    'title' => 'Devise d\'enregistrement de commande',
                    'meaning' => 'N\'affiche que les profils demandés dans une devise.',
                    'how' => 'Correspond à la Devise d\'enregistrement de commande du profil, jamais à la Devise achetée, partout sur la page. La carte Achetés sur la période sélectionnée peut donc montrer d\'autres devises : ce sont celles réellement achetées pour des profils demandés dans la devise choisie. Pour limiter par la devise achetée, utilisez le filtre Devise achetée.',
                    'change' => 'Choisissez une autre devise, ou videz le champ.',
                ],
                'purchased_currency_id' => [
                    'title' => 'Devise achetée',
                    'meaning' => 'N\'affiche que les profils dont la devise achetée est une devise donnée.',
                    'how' => 'Correspond à la Devise achetée du profil, partout sur la page. Un profil pas encore acheté n\'a pas de Devise achetée et est masqué dès que ce filtre est posé. Il se combine avec le filtre Devise d\'enregistrement de commande (les deux doivent correspondre).',
                    'change' => 'Choisissez une autre devise, ou videz le champ.',
                ],
                'supply_source' => [
                    'title' => 'Source d\'approvisionnement',
                    'meaning' => 'N\'affiche que les profils approvisionnés par une source.',
                    'how' => 'Correspond à la Source d\'approvisionnement du profil. Les sources sont : Centre d\'échange, Exportation (tiers), Exportation (propre), Lettre documentaire et Autre. Dès qu\'une source est choisie, les profils sans source sont masqués.',
                    'change' => 'Choisissez une autre source, ou videz le champ.',
                ],
                'status_id' => [
                    'title' => 'Statut',
                    'meaning' => 'N\'affiche que les profils ayant les statuts choisis. Vous pouvez en choisir plusieurs.',
                    'how' => 'Correspond au statut actuel du profil. La liste montre d\'abord les étapes de la file dans leur ordre de travail, puis les autres statuts.',
                    'change' => 'Ajoutez ou retirez des statuts. Une carte ne compte que ses propres statuts : si vous ne choisissez que Acheté, la carte En attente d\'allocation affiche zéro.',
                ],
                'stuck_days' => [
                    'title' => 'Seuil de jours par statut',
                    'meaning' => 'Le nombre de jours dans un même statut après lequel un profil est considéré comme bloqué.',
                    'how' => 'Actuellement :th_stuck jours ; vide : 30. Il fixe la couleur rouge de la colonne Jours dans le statut et l\'anomalie Trop longtemps dans un même statut.',
                    'change' => 'Saisissez un nombre entier de jours entre 1 et 365.',
                ],
                'th_due_soon' => [
                    'title' => 'Paiement bientôt dû (jours)',
                    'meaning' => 'Combien de jours à l\'avance une échéance de paiement est considérée comme proche.',
                    'how' => 'Actuellement :th_due_soon jours ; vide : 14. Il fixe l\'anomalie Paiement bientôt dû.',
                    'change' => 'Saisissez un nombre entier de jours entre 1 et 365.',
                ],
                'show_closed' => [
                    'title' => 'Afficher les profils terminés',
                    'meaning' => 'Ajoute les profils terminés au tableau. Désactivé par défaut.',
                    'how' => 'Terminé signifie que le statut est Acheté, Reçu ou Rejeté. Désactivé : le tableau ne liste que les profils en cours, toujours triés par jours dans le statut, et la carte Délais et paiements indique combien de profils terminés sont masqués. Activé : ils sont listés aussi. Les cartes, les graphiques et les anomalies comptent toujours tous les profils. Cliquer sur une ligne qui inclut des profils terminés (Achetés ce mois-ci et les deux lignes de paiement) les affiche quel que soit ce commutateur, si bien que la valeur de la ligne égale toujours les lignes affichées. Cocher un statut terminé dans le filtre Statut liste ces profils même lorsque ce commutateur est désactivé, car un choix explicite l\'emporte sur le masquage par défaut.',
                    'change' => 'Activez-le pour examiner les profils terminés, désactivez-le pour vous concentrer sur la file ouverte.',
                ],
            ],
        ],
        'cards' => [
            'title' => 'Cartes',
            'intro' => 'Chaque carte compte les profils par statut et additionne leurs montants devise par devise. Les montants de devises différentes ne sont jamais additionnés. Le total en euros convertit chaque devise séparément puis additionne les résultats. Sur les cartes en attente d\'allocation, allouées et achetées ce mois-ci, la ligne Profils et chaque ligne de devise sont cliquables : un clic filtre le tableau ci-dessous sur exactement les profils comptés par cette ligne (pour une ligne de devise, les profils de cette carte dans cette devise, avec la même devise que celle utilisée par la carte). Une pastille « Tableau filtré par » apparaît au-dessus du tableau avec un bouton « Afficher toutes les lignes », et un second clic sur la même ligne l\'efface aussi. Les cartes elles-mêmes ne changent jamais lors d\'un clic : elles continuent de compter tous les profils correspondant aux filtres. Le total en euros, la ligne des devises exclues et les deux lignes de moyennes en jours ne sont pas cliquables, car ce sont une conversion ou une moyenne et non un ensemble de profils. L\'export CSV suit le filtre cliqué. Les montants de En attente d\'allocation et de Alloués, pas encore achetés sont dans la devise demandée, tandis que ceux de Achetés sont dans la devise achetée ; une ligne de note sur chaque carte le précise. Sur la carte Délais et paiements, les deux lignes de paiement sont cliquables, mais pas les deux lignes de moyenne ni le nombre de profils terminés masqués.',
            'items' => [
                'queued' => [
                    'title' => 'En attente d\'allocation',
                    'meaning' => 'Les profils qui attendent encore que la banque alloue des devises, et les montants demandés.',
                    'how' => 'Compte les profils dont le Statut est Prêt pour allocation, En attente d\'examen par la banque ou En attente d\'approvisionnement. La ligne Profils est le nombre. Chaque ligne de devise est la somme du Montant demandé, regroupée par Devise d\'enregistrement de commande. Total en euros convertit chaque ligne de devise à son dernier taux vers l\'euro et additionne les résultats. Une devise sans taux utilisable est écartée de ce total et figure, avec son montant, sur la ligne Exclus (sans taux). Un taux n\'existe que si au moins un profil au statut Acheté ou Reçu, dont le Montant demandé et l\'Équivalent acheté sont supérieurs à zéro, a été acheté entre cette devise et l\'euro (dans un sens ou dans l\'autre). La Date d\'achat la plus récente l\'emporte ; les profils sans date d\'achat passent en dernier. Aucun taux n\'est déduit par une troisième devise, et les champs Taux de change et Taux final/efficace du profil ne sont jamais utilisés. Si aucune devise active nommée Euro n\'existe, rien ne peut être converti. Une devise dont le montant est nul n\'est pas listée comme exclue.',
                    'change' => 'Utilisez les filtres pour restreindre les profils. Pour qu\'une devise exclue entre dans le total en euros, il faut un profil Acheté ou Reçu entre cette devise et l\'euro, avec Montant demandé, Devise achetée et Équivalent acheté renseignés. Un nouveau taux est pris en compte dès l\'enregistrement de ce profil. Un profil sans devise apparaît sur une ligne marquée d\'un tiret.',
                ],
                'allocated' => [
                    'title' => 'Alloués, pas encore achetés',
                    'meaning' => 'Les profils pour lesquels la banque a alloué des devises mais dont l\'achat n\'est pas encore enregistré, et les montants concernés.',
                    'how' => 'Compte les profils dont le Statut est Alloué. Les montants sont le Montant demandé par Devise d\'enregistrement de commande. Le total en euros et la ligne Exclus (sans taux) fonctionnent exactement comme sur la carte En attente d\'allocation.',
                    'change' => 'Utilisez les filtres. Un profil quitte cette carte quand son Statut passe à autre chose qu\'Alloué, normalement Acheté.',
                ],
                'purchased_month' => [
                    'title' => 'Achetés sur la période sélectionnée',
                    'meaning' => 'Les devises achetées dans la période choisie (le mois en cours sans période), dans les devises réellement achetées.',
                    'how' => 'Compte les profils dont le Statut est Acheté ou Reçu et dont la Date d\'achat tombe entre la Date de début et la Date de fin ; avec une période, le choix de Filtrer les dates selon est ignoré pour cette carte (la Date d\'achat est toujours utilisée), donc un profil créé en juin et acheté en septembre est compté pour une période de septembre. Sans période, la carte s\'appelle Achetés ce mois-ci et utilise le mois en cours. Le mois suit le calendrier choisi dans la barre du haut : mois persan si le calendrier persan est activé, mois grégorien sinon. Les lignes de devise sont la somme de l\'Équivalent acheté regroupée par Devise achetée, et non par devise demandée ; elles peuvent donc différer des autres cartes. Le total en euros et la ligne Exclus (sans taux) fonctionnent comme sur la carte En attente d\'allocation. Un profil acheté sans Date d\'achat n\'apparaît jamais dans cette carte, avec ou sans période et quel que soit le réglage Inclure sans date (voir l\'anomalie Acheté sans date d\'achat).',
                    'change' => 'Définissez la Date de début et la Date de fin pour choisir la période, ou videz-les pour obtenir le mois en cours. Changez de calendrier dans la barre du haut pour changer le mois compté. Pour voir un profil ici, renseignez sa Date d\'achat.',
                ],
                'timing_payments' => [
                    'title' => 'Délais et paiements',
                    'meaning' => 'Le délai habituel entre la création et l\'allocation, puis entre l\'allocation et l\'achat, les profils dont le paiement est bientôt dû ou déjà en retard, et le nombre de profils terminés que le tableau masque.',
                    'how' => 'De la création à l\'allocation : moyenne du nombre de jours entre la Date de création et la Date d\'allocation. De l\'allocation à l\'achat : moyenne entre la Date d\'allocation et la Date d\'achat. Tous les profils correspondant aux filtres sont utilisés, quel que soit leur statut, mais un profil à qui il manque une des deux dates est ignoré dans cette moyenne. Le résultat est arrondi à une décimale. Un tiret signifie qu\'aucun profil n\'a les deux dates. Les deux lignes de moyenne ne sont pas cliquables. Paiement dû sous :th_due_soon jours compte les profils pas encore Reçus ni Rejetés dont la Date d\'échéance du paiement va d\'aujourd\'hui à :th_due_soon jours (la fenêtre est le filtre Paiement bientôt dû). Paiement en retard compte les mêmes profils dont la Date d\'échéance du paiement est antérieure à aujourd\'hui. Ce sont exactement les profils des anomalies Paiement bientôt dû et Paiement en retard, et un profil Acheté est compté jusqu\'à ce qu\'il soit Reçu. Les deux lignes sont cliquables et affichent leurs profils même quand Afficher les profils terminés est désactivé. Profils terminés masqués n\'est pas cliquable ; il compte les profils au statut Acheté, Reçu ou Rejeté que le tableau écarte quand Afficher les profils terminés est désactivé.',
                    'change' => 'Utilisez les filtres pour comparer banques, sociétés ou périodes. Les dates manquantes se complètent dans l\'onglet Général du profil. Modifiez la fenêtre d\'échéance proche du paiement, saisissez ou corrigez la Date d\'échéance du paiement sur le profil, ou passez le statut à Reçu une fois le paiement réglé.',
                ],
            ],
        ],
        'charts' => [
            'title' => 'Graphiques',
            'items' => [
                'funnel' => [
                    'title' => 'Profils par étape',
                    'meaning' => 'La part des profils qui se trouvent à chaque étape de la file.',
                    'how' => 'Un graphique en anneau avec une part par étape, qui compte les profils dont le Statut actuel est cette étape. Les étapes et leur ordre viennent du flux des statuts défini dans les données de base Statut. Si aucun ordre n\'y est défini, l\'ordre est : Prêt pour allocation, En attente d\'examen par la banque, En attente d\'approvisionnement, Alloué, Acheté, Reçu. Dans cet ordre par défaut, les statuts En cours de modification, Rejeté et Lettre documentaire n\'ont pas de part : ces profils figurent dans le tableau mais pas dans le graphique.',
                    'change' => 'Tous les filtres s\'appliquent. Choisir quelques statuts dans le filtre Statut laisse les autres parts à zéro.',
                ],
                'by_bank' => [
                    'title' => 'Montant en attente par banque',
                    'meaning' => 'Quel montant demandé attend une allocation dans chaque banque.',
                    'how' => 'Utilise les mêmes profils que la carte En attente d\'allocation (Prêt pour allocation, En attente d\'examen par la banque, En attente d\'approvisionnement). Chaque banque a une barre par Devise d\'enregistrement de commande, avec la somme du Montant demandé. Les devises ne sont pas converties : ne comparez que des barres de même couleur. Les profils sans banque apparaissent sous un tiret.',
                    'change' => 'Tous les filtres s\'appliquent. Le filtre Statut peut vider ce graphique s\'il ne contient aucun des trois statuts d\'attente.',
                ],
            ],
        ],
        'table' => [
            'title' => 'Tableau',
            'intro' => 'Une ligne par profil bancaire correspondant aux filtres, sans les profils terminés sauf si Afficher les profils terminés est activé. Le bouton Groupe du tableau regroupe les lignes par Banque. L\'ordre par défaut est Jours dans le statut, du plus long au plus court. Cliquez sur un en-tête de colonne pour trier, et choisissez 10, 25, 50 ou 100 lignes par page. Le sélecteur de colonnes permet d\'afficher ou de masquer celles qui le permettent.',
            'items' => [
                'bp_number' => [
                    'title' => 'Numéro BP',
                    'meaning' => 'Le numéro du profil bancaire. Survolez-le pour voir le numéro de contrat de la commande. Un clic le copie.',
                    'change' => 'Cette colonne est toujours affichée.',
                ],
                'registered_order' => [
                    'title' => 'Lié à',
                    'meaning' => 'La commande enregistrée à laquelle appartient le profil.',
                    'change' => 'Affichée par défaut, peut être masquée.',
                ],
                'bank' => [
                    'title' => 'Banque (agent)',
                    'meaning' => 'La banque qui traite le profil.',
                    'change' => 'Affichée par défaut, peut être masquée.',
                ],
                'company' => [
                    'title' => 'Société',
                    'meaning' => 'La société à laquelle appartient le profil.',
                    'change' => 'Masquée par défaut ; activez-la avec le sélecteur de colonnes.',
                ],
                'targetable' => [
                    'title' => 'Produit/Catégorie',
                    'meaning' => 'Le produit ou la catégorie auquel le profil est lié. Un tiret signifie qu\'il n\'y en a pas.',
                    'change' => 'Masquée par défaut ; activez-la avec le sélecteur de colonnes.',
                ],
                'status' => [
                    'title' => 'Statut',
                    'meaning' => 'L\'étape actuelle du profil.',
                    'change' => 'Cette colonne est toujours affichée. Modifiez le statut dans l\'onglet Général du profil.',
                ],
                'days_in_status' => [
                    'title' => 'Jours dans le statut',
                    'meaning' => 'Depuis combien de jours le profil est dans son statut actuel. Le badge est rouge au-delà de :th_stuck jours (filtre Seuil de jours par statut ; défaut 30), gris sinon. Il s\'affiche aussi pour les profils Reçu et Rejeté, bien qu\'ils ne déclenchent jamais l\'anomalie de séjour trop long.',
                    'how' => 'Nombre de jours depuis le dernier changement de Statut enregistré jusqu\'à aujourd\'hui. Si le changement de statut n\'a jamais été enregistré (les profils plus anciens n\'ont pas encore d\'historique de statut), les jours sont comptés depuis la dernière modification du profil, et le survol du badge affiche une note le précisant. Pour cette raison, enregistrer n\'importe quel champ d\'un tel profil relance le décompte.',
                    'change' => 'Le décompte repart quand le Statut change. Réglez la limite avec le filtre Seuil de jours par statut.',
                ],
                'requested_amount' => [
                    'title' => 'Montant demandé',
                    'meaning' => 'Le montant demandé à la banque, dans la devise de la colonne suivante.',
                    'change' => 'Affichée par défaut, peut être masquée. Modifiez-le dans l\'onglet Statut du virement en devise du profil.',
                ],
                'requested_currency' => [
                    'title' => 'Devise d\'enregistrement de commande',
                    'meaning' => 'La devise du montant demandé.',
                    'change' => 'Affichée par défaut, peut être masquée.',
                ],
                'purchased_equivalent' => [
                    'title' => 'Équivalent acheté',
                    'meaning' => 'Le montant réellement acheté, suivi du nom de la Devise achetée. Survolez-le pour voir le chiffre exact avec toutes ses décimales.',
                    'change' => 'Cette colonne est toujours affichée. La valeur se saisit dans l\'onglet Statut du virement en devise du profil.',
                ],
                'supply_source' => [
                    'title' => 'Source d\'approvisionnement',
                    'meaning' => 'La source par laquelle la devise est fournie. Un tiret signifie aucune.',
                    'change' => 'Masquée par défaut ; activez-la avec le sélecteur de colonnes.',
                ],
                'final_rate' => [
                    'title' => 'Taux final/effectif',
                    'meaning' => 'Le taux final du profil, affiché avec toutes ses décimales. Un tiret signifie aucun.',
                    'how' => 'Simplement affiché ; il n\'est jamais utilisé pour la conversion en euros.',
                    'change' => 'Masqué par défaut ; activez-le avec le sélecteur de colonnes.',
                ],
                'commission_rate' => [
                    'title' => 'Taux de commission (%)',
                    'meaning' => 'Le pourcentage de commission du profil. Un tiret signifie aucun.',
                    'change' => 'Masqué par défaut ; activez-le avec le sélecteur de colonnes.',
                ],
                'creation_date' => [
                    'title' => 'Date de création',
                    'meaning' => 'Le jour de création du profil. Les filtres de dates portent sur cette date, sauf indication contraire de Filtrer les dates selon.',
                    'change' => 'Masquée par défaut ; activez-la avec le sélecteur de colonnes. Les dates suivent le calendrier choisi dans la barre du haut.',
                ],
                'allocation_date' => [
                    'title' => 'Date d\'allocation',
                    'meaning' => 'Le jour où la banque a alloué les devises.',
                    'change' => 'Masquée par défaut ; activez-la avec le sélecteur de colonnes.',
                ],
                'purchase_date' => [
                    'title' => 'Date d\'achat',
                    'meaning' => 'Le jour où les devises ont été achetées. Elle décide si un profil est compté dans la carte Achetés sur la période sélectionnée.',
                    'change' => 'Masquée par défaut ; activez-la avec le sélecteur de colonnes.',
                ],
                'payment_due_date' => [
                    'title' => 'Date d\'échéance de paiement',
                    'meaning' => 'Le jour où le paiement est dû. Un badge rouge avec une icône d\'alerte signifie que l\'échéance est atteinte (le jour même compte) et que le profil n\'est pas encore Reçu ou Rejeté.',
                    'change' => 'Affichée par défaut, peut être masquée. Un profil dont l\'échéance est aujourd\'hui est déjà rouge ici, alors que les anomalies de données le listent encore sous Échéance de paiement proche ; le badge rouge disparaît quand le profil est Reçu ou Rejeté ou que son échéance est déplacée.',
                ],
                'toggle' => [
                    'title' => 'Afficher et masquer des colonnes',
                    'meaning' => 'Peuvent être masquées : Lié à, Banque (agent), Société, Produit/Catégorie, Montant demandé, Devise d\'enregistrement de commande, Source d\'approvisionnement, Taux final/effectif, Taux de commission, les trois dates et Date d\'échéance de paiement. Les colonnes Numéro BP, Statut, Jours dans le statut et Équivalent acheté sont toujours affichées.',
                ],
                'export' => [
                    'title' => 'Bouton d\'export',
                    'meaning' => 'Prépare un fichier CSV de tous les profils correspondant aux filtres actuels, et pas seulement de la page affichée.',
                    'how' => 'Le fichier est préparé en arrière-plan. Quand il est prêt, vous recevez une notification avec un lien de téléchargement. Il contient une ligne par profil avec le numéro de commande, la banque, la société, le produit ou la catégorie, la source d\'approvisionnement, le statut, les montants et devises demandés et achetés, le taux final, le taux de commission, les trois dates, l\'échéance de paiement et les jours dans le statut. Il ne tient pas compte des colonnes masquées.',
                    'change' => 'Réglez d\'abord les filtres, puis appuyez sur le bouton.',
                ],
            ],
        ],
        'issues' => [
            'title' => 'Anomalies de données',
            'intro' => 'En bas de la page, une section repliée dont le titre indique le nombre d\'anomalies ouvertes liste les enregistrements aux données manquantes ou incohérentes. Elle n\'apparaît que s\'il existe au moins une anomalie. À l\'intérieur, les pastilles Erreur, Avertissement et Information indiquent le nombre d\'anomalies de chaque gravité ; un clic sur une pastille restreint la liste à cette gravité, et Tout afficher supprime la restriction. La liste a aussi ses propres filtres Gravité et Contrôle. Les anomalies suivent les filtres de la page : leurs compteurs changent avec eux. Une erreur est une donnée fausse ou qui rend un chiffre peu fiable, un avertissement une donnée incomplète ou en retard, une information un simple repère. Un profil peut apparaître plusieurs fois, une fois par anomalie ; le compteur compte donc des anomalies, pas des profils. Chaque ligne indique la valeur actuelle, la valeur attendue, et qui a modifié le profil en dernier et quand ; pour les anomalies de statut cela vient de l\'historique des statuts, pour les autres de la dernière entrée du journal d\'audit, et un tiret signifie que rien n\'a été enregistré. Le lien Enregistrement n\'ouvre le profil que si vous avez le droit de le modifier. Si vous n\'avez pas le droit de consulter les profils bancaires, ces contrôles ne vous sont pas montrés. Les résultats sont conservés environ 5 minutes, et l\'enregistrement d\'un profil bancaire les actualise aussitôt. Une anomalie ne bloque jamais l\'enregistrement et disparaît d\'elle-même quand la donnée est corrigée. Les profils sans statut restent inclus dans les contrôles de paiement et de séjour trop long.',
            'items' => [
                'bank_profile_purchased_without_equivalent' => [
                    'title' => 'Acheté sans équivalent acheté (Erreur)',
                    'meaning' => 'Un profil marqué comme acheté n\'a pas de montant acheté : il n\'ajoute rien à la carte Achetés ce mois-ci et ne peut pas fournir de taux vers l\'euro.',
                    'how' => 'Le Statut est Acheté ou Reçu et l\'Équivalent acheté est vide ou égal à zéro.',
                    'change' => 'Ouvrez le profil, onglet Statut du virement en devise, et saisissez l\'Équivalent acheté.',
                ],
                'bank_profile_purchased_without_currency' => [
                    'title' => 'Acheté sans devise achetée (Erreur)',
                    'meaning' => 'Le montant acheté n\'a pas de devise : il apparaît sur une ligne à tiret dans Achetés ce mois-ci et ne peut pas être converti en euros.',
                    'how' => 'Le Statut est Acheté ou Reçu et aucune Devise achetée n\'est sélectionnée.',
                    'change' => 'Ouvrez le profil, onglet Statut du virement en devise, et sélectionnez la Devise achetée.',
                ],
                'bank_profile_purchased_without_purchase_date' => [
                    'title' => 'Acheté sans date d\'achat (Avertissement)',
                    'meaning' => 'Le profil n\'est jamais compté dans Achetés ce mois-ci et est ignoré dans la moyenne des jours jusqu\'à l\'achat. Pour choisir un taux vers l\'euro, les profils sans date passent en dernier.',
                    'how' => 'Le Statut est Acheté ou Reçu et la Date d\'achat est vide.',
                    'change' => 'Ouvrez le profil, onglet Général, et saisissez la Date d\'achat.',
                ],
                'bank_profile_allocated_without_allocation_date' => [
                    'title' => 'Alloué ou plus loin sans date d\'allocation (Avertissement)',
                    'meaning' => 'Le profil est ignoré dans les deux moyennes de la carte Délais et paiements.',
                    'how' => 'Le Statut est Alloué, Acheté ou Reçu et la Date d\'allocation est vide.',
                    'change' => 'Ouvrez le profil, onglet Général, et saisissez la Date d\'allocation.',
                ],
                'bank_profile_missing_exchange_rate' => [
                    'title' => 'Acheté sans taux de change (Avertissement)',
                    'meaning' => 'Le taux utilisé pour la conversion en rials est absent. Il ne change aucun chiffre de cette page, car les totaux en euros reposent sur le montant demandé et l\'équivalent acheté.',
                    'how' => 'Le Statut est Acheté ou Reçu et le Taux de change est vide ou égal à zéro.',
                    'change' => 'Ouvrez le profil, onglet Statut du virement en devise, et saisissez le Taux de change.',
                ],
                'bank_profile_requested_amount_zero' => [
                    'title' => 'Montant demandé nul ou vide (Erreur)',
                    'meaning' => 'Le profil n\'ajoute rien aux cartes et aux graphiques et ne peut pas servir à établir un taux vers l\'euro.',
                    'how' => 'Le Montant demandé est vide ou égal à zéro, quel que soit le statut.',
                    'change' => 'Ouvrez le profil, onglet Statut du virement en devise, et saisissez le Montant demandé.',
                ],
                'bank_profile_stuck_in_stage' => [
                    'title' => 'Trop longtemps dans un même statut (Avertissement)',
                    'meaning' => 'Le profil n\'a pas bougé depuis plus de jours que le Seuil de jours par statut.',
                    'how' => 'Les Jours dans le statut, calculés comme dans la colonne du tableau (depuis le dernier changement de statut, ou depuis la dernière modification s\'il n\'y a pas d\'historique), dépassent :th_stuck (défaut 30). Les profils Reçu et Rejeté ne sont pas contrôlés.',
                    'change' => 'Ouvrez le profil, onglet Général, et faites avancer le Statut quand le travail a progressé. Pour un profil sans historique de statut, toute modification enregistrée relance le décompte.',
                ],
                'bank_profile_payment_due_soon' => [
                    'title' => 'Échéance de paiement proche (Avertissement)',
                    'meaning' => 'Le paiement arrive à échéance dans la Paiement bientôt dû et le profil n\'est pas encore soldé.',
                    'how' => 'La Date d\'échéance de paiement se situe entre aujourd\'hui et dans :th_due_soon jours (défaut 14), bornes comprises, et le Statut n\'est ni Reçu ni Rejeté.',
                    'change' => 'Ouvrez le profil, onglet Général, et passez le Statut à Reçu une fois soldé. Corrigez-y la Date d\'échéance de paiement si elle était erronée.',
                ],
                'bank_profile_payment_overdue' => [
                    'title' => 'Paiement en retard (Erreur)',
                    'meaning' => 'La date de paiement est passée et le profil n\'est pas soldé. La cellule Date d\'échéance de paiement du tableau devient aussi rouge.',
                    'how' => 'La Date d\'échéance de paiement est antérieure à aujourd\'hui et le Statut n\'est ni Reçu ni Rejeté. Des statuts comme En cours de modification ou Lettre documentaire sont donc eux aussi contrôlés.',
                    'change' => 'Ouvrez le profil, onglet Général, et passez le Statut à Reçu (ou Rejeté) une fois soldé. Corrigez-y la Date d\'échéance de paiement si elle était erronée.',
                ],
                'bank_profile_status_invalid_type' => [
                    'title' => 'Statut d\'un mauvais type (Erreur)',
                    'meaning' => 'Le profil porte un statut appartenant à une autre liste ; il n\'entre dans aucune carte ni barre de graphique. La valeur actuelle indique de quel type de statut il s\'agit.',
                    'how' => 'Le Statut sélectionné ne fait pas partie des statuts de profil bancaire.',
                    'change' => 'Ouvrez le profil, onglet Général, et choisissez un Statut approprié.',
                ],
                'bank_profile_status_missing' => [
                    'title' => 'Aucun statut (Erreur)',
                    'meaning' => 'Le profil n\'est dans aucune carte ni barre de graphique. Il figure toutefois dans le tableau et dans les contrôles de paiement et de séjour trop long.',
                    'how' => 'Le profil n\'a aucun Statut.',
                    'change' => 'Ouvrez le profil, onglet Général, et choisissez le Statut.',
                ],
                'bank_profile_date_order_invalid' => [
                    'title' => 'Dates dans le mauvais ordre (Erreur)',
                    'meaning' => 'La date d\'allocation ou d\'achat précède la date qu\'elle devrait suivre : les moyennes de jours deviennent fausses ou négatives.',
                    'how' => 'La Date d\'allocation est avant la Date de création, ou la Date d\'achat est avant la Date d\'allocation (seulement si les deux dates existent). La valeur actuelle montre la paire fautive.',
                    'change' => 'Ouvrez le profil, onglet Général, et corrigez les dates.',
                ],
                'bank_profile_missing_payment_due_date' => [
                    'title' => 'Profil ouvert sans date d\'échéance de paiement (Avertissement)',
                    'meaning' => 'Sans date d\'échéance, le profil ne peut jamais apparaître comme bientôt dû ou en retard.',
                    'how' => 'Le Statut n\'est ni Reçu ni Rejeté (un profil sans statut compte comme ouvert) et la Date d\'échéance de paiement est vide.',
                    'change' => 'Ouvrez le profil, onglet Général, et saisissez la Date d\'échéance de paiement.',
                ],
                'bank_profile_supplied_without_bank' => [
                    'title' => 'Alloué ou plus loin sans banque (Erreur)',
                    'meaning' => 'Le graphique par banque et le filtre Banque ne peuvent pas placer le profil.',
                    'how' => 'Le Statut est Alloué, Acheté ou Reçu et aucune Banque (agent) n\'est sélectionnée.',
                    'change' => 'Ouvrez le profil, onglet Général, et choisissez la Banque (agent).',
                ],
                'bank_profile_no_status_history' => [
                    'title' => 'Aucun historique de statut (Information)',
                    'meaning' => 'Aucun changement de statut n\'a jamais été enregistré pour ce profil ; ses Jours dans le statut sont donc estimés depuis la dernière modification. La valeur actuelle montre la date de cette modification.',
                    'how' => 'Le profil a un Statut mais aucun changement de statut enregistré.',
                    'change' => 'L\'anomalie disparaît à la prochaine modification enregistrée du Statut du profil, car ce changement est alors consigné.',
                ],
            ],
        ],
    ],
];
