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
                'url_ifttt' => NULL,
                'url_rss_dot_app' => 'https://rss.app/feeds/4fbMLZP15RoXxPSP.xml',
                'is_active' => 0,
                'unique_xml_tag' => 'guid',
                'locale' => 'fa',
                'target_locale' => 'fa',
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
                'is_active' => 0,
                'unique_xml_tag' => 'guid',
                'locale' => 'fa',
                'target_locale' => 'fa',
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
                'is_active' => 0,
                'unique_xml_tag' => 'guid',
                'locale' => 'fa',
                'target_locale' => 'fa',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'title' => 'saber blog virgool',
                'description' => 'saber blog virgool',
                'url' => 'https://virgool.io/feed/@saber.tabatabaee',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => NULL,
                'is_active' => 0,
                'unique_xml_tag' => 'link',
                'locale' => 'fa',
                'target_locale' => 'fa',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'title' => 'Flowable | LinkedIn',
                'description' => 'Flowable | LinkedIn',
                'url' => 'https://rss.app/feeds/OlCBlCZgkAK9LJ1N.xml',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => 'https://www.linkedin.com/company/flowable-group/',
                'is_active' => 1,
                'unique_xml_tag' => NULL,
                'locale' => 'en',
                'target_locale' => 'en',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'title' => 'Camunda | LinkedIn',
                'description' => 'Camunda | LinkedIn',
                'url' => 'https://rss.app/feeds/sYlAlp95l2PDLyW7.xml',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => NULL,
                'is_active' => 1,
                'unique_xml_tag' => NULL,
                'locale' => 'en',
                'target_locale' => 'en',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 => 
            array (
                'id' => 7,
                'title' => 'Bizagi | LinkedIn',
                'description' => 'Bizagi | LinkedIn',
                'url' => 'https://rss.app/feeds/6LlkPysl9CCe1EX1.xml',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => NULL,
                'is_active' => 1,
                'unique_xml_tag' => NULL,
                'locale' => 'en',
                'target_locale' => 'en',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 => 
            array (
                'id' => 8,
                'title' => 'ProcessMaker | LinkedIn',
                'description' => 'ProcessMaker | LinkedIn',
                'url' => 'https://rss.app/feeds/zmMt3LJUphX8tTGw.xml',
                'url_ifttt' => NULL,
                'url_rss_dot_app' => NULL,
                'is_active' => 1,
                'unique_xml_tag' => NULL,
                'locale' => 'en',
                'target_locale' => 'en',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}