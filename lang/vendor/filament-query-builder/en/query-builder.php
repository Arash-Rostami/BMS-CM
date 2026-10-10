<?php

return [
    'actions' => [
        'add_rule_group' => ['label' => 'Add an alternative set (OR)'],
    ],
    'form' => [
        'or_groups' => [
            'label' => 'Any one of these sets must match',
            'block' => ['label' => 'Any one of these sets must match (OR)'],
        ],
    ],
    'item_separators' => [
        'and' => 'AND',
        'or' => 'OR',
    ],
];
