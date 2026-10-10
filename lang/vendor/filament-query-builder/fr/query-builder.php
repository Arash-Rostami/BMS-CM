<?php

return [
    'label' => 'Constructeur de requêtes',
    'max_rules_reached_tooltip' => 'Vous avez atteint le maximum de :count règles.',
    'actions' => [
        'add_rule_group' => ['label' => 'Ajouter une série alternative (OU)'],
    ],
    'form' => [
        'or_groups' => [
            'label' => 'Une seule de ces séries doit correspondre',
            'group' => ['label' => 'Groupe'],
            'block' => ['label' => 'Une seule de ces séries doit correspondre (OU)'],
        ],
    ],
    'item_separators' => [
        'and' => 'ET',
        'or' => 'OU',
    ],
    'operators' => [
        'relationship' => [
            'is_related_to' => [
                'summary' => [
                    'values_glue' => ['final' => ' ou '],
                ],
            ],
        ],
        'date' => [
            'unit_labels' => [
                'second' => 'Secondes',
                'minute' => 'Minutes',
                'hour' => 'Heures',
                'day' => 'Jours',
                'week' => 'Semaines',
                'month' => 'Mois',
                'quarter' => 'Trimestres',
                'year' => 'Années',
            ],
            'presets' => [
                'past_decade' => 'Dernière décennie',
                'past_5_years' => '5 dernières années',
                'past_2_years' => '2 dernières années',
                'past_year' => 'Dernière année',
                'past_6_months' => '6 derniers mois',
                'past_quarter' => 'Dernier trimestre',
                'past_month' => 'Dernier mois',
                'past_2_weeks' => '2 dernières semaines',
                'past_week' => 'Dernière semaine',
                'past_hour' => 'Dernière heure',
                'past_minute' => 'Dernière minute',
                'this_decade' => 'Cette décennie',
                'this_year' => 'Cette année',
                'this_quarter' => 'Ce trimestre',
                'this_month' => 'Ce mois-ci',
                'today' => 'Aujourd\'hui',
                'this_hour' => 'Cette heure',
                'this_minute' => 'Cette minute',
                'next_minute' => 'Minute suivante',
                'next_hour' => 'Heure suivante',
                'next_week' => 'Semaine prochaine',
                'next_2_weeks' => '2 prochaines semaines',
                'next_month' => 'Mois prochain',
                'next_quarter' => 'Trimestre prochain',
                'next_6_months' => '6 prochains mois',
                'next_year' => 'Année prochaine',
                'next_2_years' => '2 prochaines années',
                'next_5_years' => '5 prochaines années',
                'next_decade' => 'Prochaine décennie',
                'custom' => 'Personnalisé',
            ],
            'form' => [
                'mode' => [
                    'label' => 'Type de date',
                    'options' => ['absolute' => 'Date précise', 'relative' => 'Période glissante'],
                ],
                'preset' => ['label' => 'Période'],
                'relative_value' => ['label' => 'Combien'],
                'relative_unit' => ['label' => 'Unité de temps'],
                'tense' => [
                    'label' => 'Temps',
                    'options' => ['past' => 'Passé', 'future' => 'Futur'],
                ],
            ],
        ],
    ],
];
