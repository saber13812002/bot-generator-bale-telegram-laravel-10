<?php

return [
    'genres_per_page' => 5,
    'books_per_page' => 4,

    'plans' => [
        'free' => [
            'limit' => 3,
            'price' => 0,
            'label_key' => 'book_library.plan.free',
        ],
        'plan_100' => [
            'limit' => 100,
            'price' => 990000,
            'label_key' => 'book_library.plan.plan_100',
        ],
        'plan_300' => [
            'limit' => 300,
            'price' => 2490000,
            'label_key' => 'book_library.plan.plan_300',
        ],
        'plan_1000' => [
            'limit' => 1000,
            'price' => 6990000,
            'label_key' => 'book_library.plan.plan_1000',
        ],
    ],

    'paid_plans' => ['plan_100', 'plan_300', 'plan_1000'],
];
