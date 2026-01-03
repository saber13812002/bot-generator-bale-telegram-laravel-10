<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RssChannelOriginsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('rss_channel_origins')->delete();
        
        \DB::table('rss_channel_origins')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Bale',
                'slug' => 'bale',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Rocket',
                'slug' => 'rocket',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Telegram',
                'slug' => 'telegram',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Sorush plus',
                'slug' => 'splus',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Eitaa',
                'slug' => 'eitaa',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Gap',
                'slug' => 'gap',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'Sms',
                'slug' => 'sms',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'Blog',
                'slug' => 'blog',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'WhatsApp',
                'slug' => 'whatsapp',
                'created_at' => '2024-06-16 14:22:25',
                'updated_at' => '2024-06-16 14:22:25',
            ),
        ));
        
        
    }
}