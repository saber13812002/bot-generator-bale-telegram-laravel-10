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
                'id' => 1,
                'title' => 'saber',
                'slug' => 'saber',
                'token' => '1775842974:0cefc40d5eaab5a8d2ca19f77ca13a5463be5994',
                'target_id' => '485750575',
                'type' => 'private',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'title' => 'eitaa log pardisania',
                'slug' => 'eitaalogpardisania',
                'token' => 'bot1967:e7b12e5f-77ed-4c67-8392-200214f9257a',
                'target_id' => '8419225',
                'type' => 'channel',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}