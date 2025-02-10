<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RssChannelsTableSeeder extends Seeder
{
// todo test after amniat replace token with env please test this seeder
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('rss_channels')->delete();

        \DB::table('rss_channels')->insert(array (
            0 =>
            array (
                'id' => 3,
                'origin_id' => 1,
                'title' => 'saber2',
                'slug' => 'saber2',
                'token' => env('BOT_HADITH_TOKEN_BALE'),
                'target_id' => '485750575',
                'type' => 'private',
                'created_at' => NULL,
                'updated_at' => '2024-06-16 11:35:41',
            ),
            1 =>
            array (
                'id' => 2,
                'origin_id' => 5,
                'title' => 'eitaa log pardisania',
                'slug' => 'eitaalogpardisania',
                'token' => env('BOT_EITAA_TOKEN_SABER'),
                'target_id' => '8419225',
                'type' => 'channel',
                'created_at' => NULL,
                'updated_at' => '2024-06-16 12:24:17',
            ),
            2 =>
            array (
                'id' => 1,
                'origin_id' => 1,
                'title' => 'bale kasra digital',
                'slug' => 'kasra bale',
                'token' => env('BOT_BALE_TOKEN_KASRA'),
                'target_id' => '5517896720',
                'type' => 'channel',
                'created_at' => NULL,
                'updated_at' => '2024-06-16 11:38:26',
            ),
        ));


    }
}

