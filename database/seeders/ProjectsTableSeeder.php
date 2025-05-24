<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProjectsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('projects')->delete();
        
        \DB::table('projects')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'voice-of-islam',
                'slug' => 'voice-of-islam',
                'need_registration' => 1,
                'default_bots_text' => NULL,
                'start_date' => '1982-02-02',
                'description' => 'روبات صدای اسلام',
                'brief' => NULL,
                'status' => 'active',
                'class' => NULL,
                'points_per_month' => 0,
                'points_per_year' => 0,
                'points_per_lifetime' => 0,
                'points_per_month_for_referral' => 0,
                'points_per_year_for_referral' => 0,
                'points_per_lifetime_for_referral' => 0,
                'created_at' => '2025-05-23 19:57:21',
                'updated_at' => '2025-05-23 19:57:21',
            ),
        ));
        
        
    }
}