<?php

return [
    'tab_label' => 'Guide de Référence : Demande d\'Achat',
    'scope_label' => 'Étape de demande et d\'approbation : Demande de renseignements → PI → PO',
    'covers' => ['purchaseRequest', 'proformaInvoice'],

    'tips' => [
        'Un prix anormalement bas peut signaler une qualité médiocre ou une fraude — comparez-le toujours à la moyenne du marché.',
    ],

    'terms' => [
        ['term' => 'Demande de renseignements (Inquiry)', 'definition' => 'Demande initiale et informelle adressée à un fournisseur pour savoir si les marchandises requises peuvent être fournies ; généralement sans engagement ferme sur le prix.'],
        ['term' => 'RFQ (Demande de devis)', 'definition' => 'Demande formelle pour un prix précis basé sur des spécifications techniques définies, une quantité et des conditions de livraison.'],
        ['term' => 'Devis (Quotation)', 'definition' => 'Réponse du fournisseur à une demande de devis ; inclut le prix unitaire, les conditions de paiement proposées et la validité du devis, mais n\'est pas encore un document commercial formel.'],
        ['term' => 'Facture Proforma (PI)', 'definition' => 'Document formel final émis après accord des deux parties ; la base légale pour l\'enregistrement de commande et l\'allocation de devises.'],
        ['term' => 'PO (Bon de commande)', 'definition' => 'Bon de commande officiel émis par l\'acheteur sur la base d\'une proforma approuvée afin que la production/l\'expédition puisse commencer.'],
        ['term' => 'MOQ (Quantité minimale de commande)', 'definition' => 'Quantité minimale acceptable par commande de la part du fournisseur.'],
        ['term' => 'Délai de réalisation (Lead Time)', 'definition' => 'Temps écoulé entre la confirmation de la commande et le moment où les marchandises sont prêtes à être chargées.'],
        ['term' => 'L/C (Lettre de crédit)', 'definition' => 'Crédit documentaire ; une méthode de paiement garantie par une banque.'],
        ['term' => 'T/T (Virement télégraphique)', 'definition' => 'Virement bancaire direct.'],
        ['term' => 'Échantillon / Échantillon de pré-expédition', 'definition' => 'Prototype ou échantillon avant expédition pour l\'approbation de la qualité.'],
    ],

    'process' => [
        ['title' => 'Enregistrer la demande d\'achat interne', 'description' => 'Besoin, spécifications techniques et budget approximatif.'],
        ['title' => 'Approbation interne', 'description' => 'Par le responsable / l\'unité concernée.'],
        ['title' => 'Identifier les fournisseurs potentiels', 'description' => 'Base de données interne, salons professionnels, plateformes B2B telles qu\'Alibaba.'],
        ['title' => 'Envoyer une demande de renseignements', 'description' => 'À plusieurs fournisseurs simultanément.'],
        ['title' => 'Recevoir les devis et comparer', 'description' => 'Prix, conditions de paiement et délai de réalisation.'],
        ['title' => 'Négocier le prix et les conditions', 'description' => 'Parvenir à un accord final.'],
        ['title' => 'Demander et examiner un échantillon', 'description' => 'Si nécessaire.'],
        ['title' => 'Obtenir la proforma finale', 'description' => 'Auprès du fournisseur sélectionné.'],
        ['title' => 'Approuver la proforma et transmettre', 'description' => 'Transférer le dossier à l\'enregistrement de commande.'],
    ],

    'dos' => [
        'Vérifier le dossier d\'enregistrement de l\'entreprise dans le pays d\'origine (année de création, licence d\'exploitation).',
        'Solliciter des sociétés d\'inspection internationales (SGS, Bureau Veritas et similaires) pour un audit d\'usine.',
        'Vérifier les certificats de qualité pertinents pour les marchandises (ISO, CE, etc.).',
        'Demander un échantillon et évaluer la qualité avant de passer une commande de grand volume.',
        'Évaluer la capacité de production du fournisseur par rapport au volume de la commande (prévient les retards de livraison).',
        'Comparer le prix proposé avec la moyenne du marché (un prix inhabituellement bas peut signaler une qualité médiocre ou un risque).',
    ],

    'donts' => [
        'Accepter un paiement anticipé inhabituel — une demande d\'acompte hors du commun est un signal d\'alarme.',
        'Ignorer les restrictions bancaires/sanctions sur le transfert de fonds vers un pays ou une entreprise donnés, compte tenu des conditions spécifiques de transfert de devises de l\'Iran.',
    ],

    'as_of' => 'Juillet 2026',

    'media' => [
        'video' => 'request_approval-fa.mp4',
        'audio' => 'request_approval-fa.m4a',
        'poster' => 'request_approval-fa.jpg',
    ],
];
