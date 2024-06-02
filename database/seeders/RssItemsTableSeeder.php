<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RssItemsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('rss_items')->delete();
        
        \DB::table('rss_items')->insert(array (
            0 => 
            array (
                'id' => 1,
                'title' => 'tooljet youtubr',
                'description' => 'tooljet youtubr',
                'url' => 'https://www.youtube.com/channel/UCf1p2G5Z7fPpvlBPf4l2I1w',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => 'https://rss.app/feeds/4fbMLZP15RoXxPSP.xml',
                'created_at' => '2024-06-02 20:54:16',
                'updated_at' => '2024-06-02 21:04:20',
            ),
        ));
        
        
    }
}