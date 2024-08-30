<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SongsaraMoodsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('songsara_moods')->delete();
        
        \DB::table('songsara_moods')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'spring',
                'slug' => 'spring',
                'created_at' => '2024-08-16 17:49:20',
                'updated_at' => '2024-08-16 17:49:20',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'relaxing',
                'slug' => 'relaxing',
                'created_at' => '2024-08-16 17:49:22',
                'updated_at' => '2024-08-16 17:49:22',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'happy',
                'slug' => 'happy',
                'created_at' => '2024-08-16 17:49:23',
                'updated_at' => '2024-08-16 17:49:23',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'epic',
                'slug' => 'epic',
                'created_at' => '2024-08-16 17:49:24',
                'updated_at' => '2024-08-16 17:49:24',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'backgroundmusic',
                'slug' => 'backgroundmusic',
                'created_at' => '2024-08-16 17:49:25',
                'updated_at' => '2024-08-16 17:49:25',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'chill',
                'slug' => 'chill',
                'created_at' => '2024-08-16 17:49:26',
                'updated_at' => '2024-08-16 17:49:26',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'no-copyright',
                'slug' => 'no-copyright',
                'created_at' => '2024-08-16 17:49:27',
                'updated_at' => '2024-08-16 17:49:27',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'positive-energy',
                'slug' => 'positive-energy',
                'created_at' => '2024-08-16 17:49:28',
                'updated_at' => '2024-08-16 17:49:28',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'depressed',
                'slug' => 'depressed',
                'created_at' => '2024-08-16 17:49:29',
                'updated_at' => '2024-08-16 17:49:29',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'focus',
                'slug' => 'focus',
                'created_at' => '2024-08-16 17:49:31',
                'updated_at' => '2024-08-16 17:49:31',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'romantic',
                'slug' => 'romantic',
                'created_at' => '2024-08-16 17:49:32',
                'updated_at' => '2024-08-16 17:49:32',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => 'study',
                'slug' => 'study',
                'created_at' => '2024-08-16 17:49:33',
                'updated_at' => '2024-08-16 17:49:33',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => 'excitedly',
                'slug' => 'excitedly',
                'created_at' => '2024-08-16 17:49:34',
                'updated_at' => '2024-08-16 17:49:34',
            ),
            13 => 
            array (
                'id' => 14,
                'name' => 'contemplative',
                'slug' => 'contemplative',
                'created_at' => '2024-08-16 17:49:35',
                'updated_at' => '2024-08-16 17:49:35',
            ),
            14 => 
            array (
                'id' => 15,
                'name' => 'sport',
                'slug' => 'sport',
                'created_at' => '2024-08-16 17:49:37',
                'updated_at' => '2024-08-16 17:49:37',
            ),
            15 => 
            array (
                'id' => 16,
                'name' => 'dramatic',
                'slug' => 'dramatic',
                'created_at' => '2024-08-16 17:49:38',
                'updated_at' => '2024-08-16 17:49:38',
            ),
            16 => 
            array (
                'id' => 17,
                'name' => 'dreamy',
                'slug' => 'dreamy',
                'created_at' => '2024-08-16 17:49:39',
                'updated_at' => '2024-08-16 17:49:39',
            ),
            17 => 
            array (
                'id' => 18,
                'name' => 'sentimental',
                'slug' => 'sentimental',
                'created_at' => '2024-08-16 17:49:40',
                'updated_at' => '2024-08-16 17:49:40',
            ),
            18 => 
            array (
                'id' => 19,
                'name' => 'rainy',
                'slug' => 'rainy',
                'created_at' => '2024-08-16 17:49:42',
                'updated_at' => '2024-08-16 17:49:42',
            ),
            19 => 
            array (
                'id' => 20,
                'name' => 'phonetic',
                'slug' => 'phonetic',
                'created_at' => '2024-08-16 17:49:43',
                'updated_at' => '2024-08-16 17:49:43',
            ),
            20 => 
            array (
                'id' => 21,
                'name' => 'imagination',
                'slug' => 'imagination',
                'created_at' => '2024-08-16 17:49:44',
                'updated_at' => '2024-08-16 17:49:44',
            ),
            21 => 
            array (
                'id' => 22,
                'name' => 'baby-sleep',
                'slug' => 'baby-sleep',
                'created_at' => '2024-08-16 17:49:45',
                'updated_at' => '2024-08-16 17:49:45',
            ),
            22 => 
            array (
                'id' => 23,
                'name' => 'sad-piano',
                'slug' => 'sad-piano',
                'created_at' => '2024-08-16 17:49:46',
                'updated_at' => '2024-08-16 17:49:46',
            ),
            23 => 
            array (
                'id' => 24,
                'name' => 'hopeful',
                'slug' => 'hopeful',
                'created_at' => '2024-08-16 17:49:48',
                'updated_at' => '2024-08-16 17:49:48',
            ),
            24 => 
            array (
                'id' => 25,
                'name' => 'trailer',
                'slug' => 'trailer',
                'created_at' => '2024-08-16 17:49:50',
                'updated_at' => '2024-08-16 17:49:50',
            ),
            25 => 
            array (
                'id' => 26,
                'name' => 'relax-guitar',
                'slug' => 'relax-guitar',
                'created_at' => '2024-08-16 17:49:51',
                'updated_at' => '2024-08-16 17:49:51',
            ),
            26 => 
            array (
                'id' => 27,
                'name' => 'night',
                'slug' => 'night',
                'created_at' => '2024-08-16 17:49:52',
                'updated_at' => '2024-08-16 17:49:52',
            ),
            27 => 
            array (
                'id' => 28,
                'name' => 'sleep',
                'slug' => 'sleep',
                'created_at' => '2024-08-16 17:49:53',
                'updated_at' => '2024-08-16 17:49:53',
            ),
            28 => 
            array (
                'id' => 29,
                'name' => 'piano-solo',
                'slug' => 'piano-solo',
                'created_at' => '2024-08-16 17:49:54',
                'updated_at' => '2024-08-16 17:49:54',
            ),
            29 => 
            array (
                'id' => 30,
                'name' => 'summer',
                'slug' => 'summer',
                'created_at' => '2024-08-16 17:49:55',
                'updated_at' => '2024-08-16 17:49:55',
            ),
            30 => 
            array (
                'id' => 31,
                'name' => 'autumn',
                'slug' => 'autumn',
                'created_at' => '2024-08-16 17:49:56',
                'updated_at' => '2024-08-16 17:49:56',
            ),
            31 => 
            array (
                'id' => 32,
                'name' => 'winter',
                'slug' => 'winter',
                'created_at' => '2024-08-16 17:49:57',
                'updated_at' => '2024-08-16 17:49:57',
            ),
        ));
        
        
    }
}