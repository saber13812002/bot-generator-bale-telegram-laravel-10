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
        'unlimited' => [
            'limit' => 999999,
            'price' => 10000000,
            // قیمت نمایشی خط‌خورده (دموی ظاهری - هیچ پرداخت واقعی رخ نمی‌دهد)
            'list_price' => 20000000,
            'label_key' => 'book_library.plan.unlimited',
        ],
    ],

    'paid_plans' => ['plan_100', 'plan_300', 'plan_1000', 'unlimited'],

    // ============================================================
    // Milestone reward engine (listening reward)
    //
    // When a paid plan is confirmed, the user is told:
    //   "Receive :target books -> get :bonus books free +
    //    a 100% discount code that auto-activates (library free to the end)"
    //
    // "Listening" is not verified: reaching the Nth delivered audio
    // (books_used >= reward_target) is considered the milestone.
    // ============================================================
    'rewards' => [
        'enabled' => true,
        // Plans that arm a milestone on confirmation (free/unlimited never arm)
        'plans' => ['plan_100', 'plan_300', 'plan_1000'],
        // Listen/receive N -> receive N * bonus_multiplier books for free
        'bonus_multiplier' => 2,
        // Effect of the auto-activated 100% code: grant the remaining library for free
        'win_effect' => 'unlimited',
        'discount' => [
            'percent' => 100,
            // Symbolic display amount shown in the win message (demo only)
            'display_amount' => 5000000,
            'code_prefix' => 'LIB100',
        ],
    ],
];
