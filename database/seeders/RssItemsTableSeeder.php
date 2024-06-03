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
                'url' => 'http://127.0.0.1:8001/comments/feed/6',
//                'url' => 'https://www.youtube.com/channel/UCf1p2G5Z7fPpvlBPf4l2I1w',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => 'https://rss.app/feeds/4fbMLZP15RoXxPSP.xml',
                'is_active' => 1,
                'unique_xml_tag' => 'guid',
                'created_at' => '2024-06-02 20:54:16',
                'updated_at' => '2024-06-02 21:04:20',
            ),
            1 =>
            array (
                'id' => 2,
                'title' => 'saber blog',
                'description' => 'saber blog',
                'url' => 'http://127.0.0.1:8001/comments/feed/32',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => NULL,
                'is_active' => 1,
                'unique_xml_tag' => 'guid',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 =>
            array (
                'id' => 3,
                'title' => 'saber blog post',
                'description' => 'saber blog post',
                'url' => 'http://127.0.0.1:8001/posts/feed/32',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => NULL,
                'is_active' => 1,
                'unique_xml_tag' => 'guid',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));


    }
}
