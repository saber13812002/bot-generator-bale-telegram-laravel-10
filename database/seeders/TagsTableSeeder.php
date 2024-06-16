<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TagsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('tags')->delete();
        
        \DB::table('tags')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => '{"fa":"linkedin"}',
                'slug' => '{"fa":"linkedin"}',
                'type' => NULL,
                'order_column' => 1,
                'created_at' => '2024-06-05 10:34:49',
                'updated_at' => '2024-06-05 10:34:49',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => '{"fa":"bpms"}',
                'slug' => '{"fa":"bpms"}',
                'type' => NULL,
                'order_column' => 2,
                'created_at' => '2024-06-05 10:28:59',
                'updated_at' => '2024-06-05 10:28:59',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => '{"fa":"twitter"}',
                'slug' => '{"fa":"twitter"}',
                'type' => NULL,
                'order_column' => 3,
                'created_at' => '2024-06-06 05:25:16',
                'updated_at' => '2024-06-06 05:25:16',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => '{"fa":"flowable"}',
                'slug' => '{"fa":"flowable"}',
                'type' => NULL,
                'order_column' => 4,
                'created_at' => '2024-06-06 05:25:16',
                'updated_at' => '2024-06-06 05:25:16',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => '{"fa":"youtube"}',
                'slug' => '{"fa":"youtube"}',
                'type' => NULL,
                'order_column' => 5,
                'created_at' => '2024-06-06 05:25:41',
                'updated_at' => '2024-06-06 05:25:41',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => '{"fa":"camunda"}',
                'slug' => '{"fa":"camunda"}',
                'type' => NULL,
                'order_column' => 6,
                'created_at' => '2024-06-06 05:26:07',
                'updated_at' => '2024-06-06 05:26:07',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => '{"fa":"process maker"}',
                'slug' => '{"fa":"process-maker"}',
                'type' => NULL,
                'order_column' => 7,
                'created_at' => '2024-06-06 05:26:45',
                'updated_at' => '2024-06-06 05:26:45',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => '{"fa":"process_maker"}',
                'slug' => '{"fa":"process-maker"}',
                'type' => NULL,
                'order_column' => 8,
                'created_at' => '2024-06-06 05:26:45',
                'updated_at' => '2024-06-06 05:26:45',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => '{"fa":"processmaker"}',
                'slug' => '{"fa":"processmaker"}',
                'type' => NULL,
                'order_column' => 9,
                'created_at' => '2024-06-06 05:26:45',
                'updated_at' => '2024-06-06 05:26:45',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => '{"fa":"blog"}',
                'slug' => '{"fa":"blog"}',
                'type' => NULL,
                'order_column' => 10,
                'created_at' => '2024-06-06 17:24:31',
                'updated_at' => '2024-06-06 17:24:31',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => '{"fa":"facebook"}',
                'slug' => '{"fa":"facebook"}',
                'type' => NULL,
                'order_column' => 11,
                'created_at' => '2024-06-07 14:38:14',
                'updated_at' => '2024-06-07 14:38:14',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => '{"fa":"tooljet"}',
                'slug' => '{"fa":"tooljet"}',
                'type' => NULL,
                'order_column' => 12,
                'created_at' => '2024-06-10 07:14:03',
                'updated_at' => '2024-06-10 07:14:03',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => '{"fa":"stackoverflow"}',
                'slug' => '{"fa":"stackoverflow"}',
                'type' => NULL,
                'order_column' => 13,
                'created_at' => '2024-06-11 06:16:09',
                'updated_at' => '2024-06-11 06:16:09',
            ),
        ));
        
        
    }
}