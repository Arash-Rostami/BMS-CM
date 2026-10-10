<?php

return [
    'actions' => [
        'add_rule_group' => ['label' => 'افزودن مجموعهٔ جایگزین (یا)'],
    ],
    'form' => [
        'or_groups' => [
            'label' => 'هر یک از این مجموعه‌ها باید برقرار باشد',
            'group' => ['label' => 'گروه'],
            'block' => ['label' => 'هر یک از این مجموعه‌ها باید برقرار باشد (یا)'],
        ],
    ],
    'item_separators' => [
        'and' => 'و',
        'or' => 'یا',
    ],
    'max_rules_reached_tooltip' => 'به حداکثر :count شرط رسیده‌اید.',
];
