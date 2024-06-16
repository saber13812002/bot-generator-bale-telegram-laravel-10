<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RssChannelsTableSeeder extends Seeder
{

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
                'token' => '1775842974:0cefc40d5eaab5a8d2ca19f77ca13a5463be5994',
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
                'token' => 'bot1967:e7b12e5f-77ed-4c67-8392-200214f9257a',
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
                'token' => '1550000874:dQojAFZsKDZ3JAD52AAqyXTfsmP283gRrbImlOes',
                'target_id' => '5517896720',
                'type' => 'channel',
                'created_at' => NULL,
                'updated_at' => '2024-06-16 11:38:26',
            ),
        ));
        
        
    }
}