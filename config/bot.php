<?php


return [
    'childbotwebhookurl' => env('APP_URL') . '/api/webhook-bot-children',
    'childbotapproveurl' => env('APP_URL') . '/api/approve',
    'ourbots' => [
        'getchatid' => [
            'bale' => 'https://ble.ir/Get_idbot  https://ble.ir/Getbotidbot  https://ble.ir/Get_chat_id_bot',
            'telegram' => 'https://t.me/Get_idbot ',
            'gap' => 'https://gap.im/getidbot  ',
//            'soroosh' => '  ',
        ]
    ],
    'base_url' => [
        'bale' => 'https://ble.ir/',
        'telegram' => 'https://t.me/',
        'gap' => 'https://gap.ir/',
    ],
    'admin_bots_link' => env('ADMIN_BOTS_LINK', 'https://ble.ir'),
    'hidden_admin_kie_command' => env('HIDDEN_ADMIN_KIE_COMMAND', '/adminkie'),
    'tokens' =>[
        'bale' =>[
            'robat' => env('ROBAT_BALE_TOKEN_TEST'),
            ]
    ],

];
