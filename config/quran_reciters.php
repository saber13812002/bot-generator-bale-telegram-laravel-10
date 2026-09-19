<?php

/*
|--------------------------------------------------------------------------
| Quran Reciters (Qari) Registry
|--------------------------------------------------------------------------
|
| Single source of truth for the Quran bot reciters.
|
| Each reciter defines:
|  - name:  localized display names (fallback: en)
|  - url:   base URL for per-ayah audio files (must end with "/")
|  - file:  file-name pattern, supports these placeholders:
|             {sura_3}  -> sura number, 3 digits (e.g. 002)
|             {aya_3}   -> ayah number, 3 digits (e.g. 025)
|             {ayah_id} -> global ayah id (1..6236)
|
| The bot builds the final audio URL as: url + file + ".mp3"
|
*/

return [

    // Reciter used when a user has no saved preference.
    'default' => 'parhizgar',

    'reciters' => [

        'parhizgar' => [
            'name' => [
                'en' => 'Parhizgar',
                'fa' => 'پرهیزگار',
            ],
            'url'  => 'https://tanzil.net/res/audio/parhizgar/',
            'file' => '{sura_3}{aya_3}',
        ],

        'alafasy' => [
            'name' => [
                'en' => 'Alafasy',
                'fa' => 'العفاسی',
            ],
            'url'  => 'https://cdn.islamic.network/quran/audio/128/ar.alafasy/',
            'file' => '{ayah_id}',
        ],

        'hudhaify' => [
            'name' => [
                'en' => 'Hudhaify',
                'fa' => 'هذیفه',
            ],
            'url'  => 'https://cdn.islamic.network/quran/audio/128/ar.hudhaify/',
            'file' => '{ayah_id}',
        ],

        'sudais' => [
            'name' => [
                'en' => 'Abdur-Rahman As-Sudais',
                'fa' => 'الدّسیس',
            ],
            'url'  => 'https://cdn.islamic.network/quran/audio/128/ar.abdurrahmaansudais/',
            'file' => '{ayah_id}',
        ],

        'shuraym' => [
            'name' => [
                'en' => 'Saad Al-Shuraym',
                'fa' => 'الشُّریم',
            ],
            'url'  => 'https://cdn.islamic.network/quran/audio/128/ar.saoodshuraym/',
            'file' => '{ayah_id}',
        ],

        'minshawi' => [
            'name' => [
                'en' => 'Minshawi',
                'fa' => 'المنشاوی',
            ],
            'url'  => 'https://cdn.islamic.network/quran/audio/128/ar.minshawi/',
            'file' => '{ayah_id}',
        ],

    ],

];
