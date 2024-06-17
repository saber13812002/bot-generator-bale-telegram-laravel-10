<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TaggablesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('taggables')->delete();
        
        \DB::table('taggables')->insert(array (
            0 => 
            array (
                'tag_id' => 1,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 5,
            ),
            1 => 
            array (
                'tag_id' => 1,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 6,
            ),
            2 => 
            array (
                'tag_id' => 1,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 7,
            ),
            3 => 
            array (
                'tag_id' => 1,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 8,
            ),
            4 => 
            array (
                'tag_id' => 1,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 13,
            ),
            5 => 
            array (
                'tag_id' => 2,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 5,
            ),
            6 => 
            array (
                'tag_id' => 3,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 11,
            ),
            7 => 
            array (
                'tag_id' => 3,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 15,
            ),
            8 => 
            array (
                'tag_id' => 4,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 12,
            ),
            9 => 
            array (
                'tag_id' => 4,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 13,
            ),
            10 => 
            array (
                'tag_id' => 4,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 14,
            ),
            11 => 
            array (
                'tag_id' => 4,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 15,
            ),
            12 => 
            array (
                'tag_id' => 5,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 10,
            ),
            13 => 
            array (
                'tag_id' => 5,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 14,
            ),
            14 => 
            array (
                'tag_id' => 6,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 9,
            ),
            15 => 
            array (
                'tag_id' => 6,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 10,
            ),
            16 => 
            array (
                'tag_id' => 6,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 11,
            ),
            17 => 
            array (
                'tag_id' => 7,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 8,
            ),
            18 => 
            array (
                'tag_id' => 7,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 16,
            ),
            19 => 
            array (
                'tag_id' => 8,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 8,
            ),
            20 => 
            array (
                'tag_id' => 8,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 16,
            ),
            21 => 
            array (
                'tag_id' => 9,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 8,
            ),
            22 => 
            array (
                'tag_id' => 9,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 16,
            ),
            23 => 
            array (
                'tag_id' => 10,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 9,
            ),
            24 => 
            array (
                'tag_id' => 10,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 12,
            ),
            25 => 
            array (
                'tag_id' => 10,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => 16,
            ),
            26 => 
            array (
                'tag_id' => 14,
                'taggable_type' => 'App\\Models\\RssChannel',
                'taggable_id' => 2,
            ),
            27 => 
            array (
                'tag_id' => 15,
                'taggable_type' => 'App\\Models\\RssChannel',
                'taggable_id' => 3,
            ),
        ));
        
        
    }
}