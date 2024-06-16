<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RssBusinessesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('rss_businesses')->delete();
        
        \DB::table('rss_businesses')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'depna',
                'admin_user_id' => 1,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'saber',
                'admin_user_id' => 2,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}