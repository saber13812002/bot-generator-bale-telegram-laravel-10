<?php

return [
    'product_link_label' => 'مشاهده صفحه محصول',

    'channels' => [
        'eitaa' => [
            'url' => 'https://eitaa.com/sharabebeheshti',
            'label' => 'کانال ایتا',
        ],
        'bale' => [
            'url' => 'https://ble.ir/sharabebeheshti',
            'label' => 'کانال بله',
        ],
        'telegram' => [
            'url' => 'https://t.me/sharabebeheshti_ir',
            'label' => 'کانال تلگرام',
        ],
        'splus' => [
            'url' => env('SHARABE_BEHESHTI_SPLUS_URL', ''),
            'label' => 'کانال سروش',
        ],
    ],
];
