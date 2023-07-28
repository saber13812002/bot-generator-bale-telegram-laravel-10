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
    ]
];
