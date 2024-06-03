<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RssPostItemsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('rss_post_items')->delete();
        
        \DB::table('rss_post_items')->insert(array (
            0 => 
            array (
                'id' => 2,
                'rss_item_id' => 1,
                'title' => '',
                'link' => 'http://127.0.0.1:8001/api/v1/comments/10',
                'description' => 'و عجل لنا ظهور ه...',
                'pub_date' => '2023-05-11 04:48:36',
                'created_at' => '2024-06-02 22:36:42',
                'updated_at' => '2024-06-02 22:36:42',
            ),
            1 => 
            array (
                'id' => 3,
                'rss_item_id' => 2,
                'title' => '',
                'link' => 'http://127.0.0.1:8001/api/v1/comments/14',
                'description' => 'asdfasdfasdf...',
                'pub_date' => '2024-06-03 00:39:21',
                'created_at' => '2024-06-02 22:40:41',
                'updated_at' => '2024-06-02 22:40:41',
            ),
            2 => 
            array (
                'id' => 4,
                'rss_item_id' => 3,
                'title' => '<p>asdfasdf</p>',
                'link' => 'http://127.0.0.1:8001/posts/adsfasdfadsf',
                'description' => '<p>asdfasdf</p>',
                'pub_date' => '2024-05-27 02:16:00',
                'created_at' => '2024-06-02 22:47:34',
                'updated_at' => '2024-06-02 22:47:34',
            ),
            3 => 
            array (
                'id' => 5,
                'rss_item_id' => 3,
                'title' => '<p>asdfaaaaaaaaaaaaaaa</p>',
                'link' => 'http://127.0.0.1:8001/posts/sadfsadf',
                'description' => '<p>asdfaaaaaaaaaaaaaaa</p>',
                'pub_date' => '2024-06-01 02:18:00',
                'created_at' => '2024-06-02 22:49:13',
                'updated_at' => '2024-06-02 22:49:13',
            ),
        ));
        
        
    }
}