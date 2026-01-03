<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Generate sample data
        $data = [];
        $startDate = Carbon::now()->subDays(30); // Start from 30 days ago

        for ($i = 0; $i < 30; $i++) {
            $data[] = [
                'user_id' => rand(1, 5), // Assuming you have 5 users
                'date' => $startDate->copy()->addDays($i)->format('Y-m-d'),
                'page_count' => rand(1, 100), // Random page counts between 1 and 100
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert data into the activity_logs table
        DB::table('activity_logs')->insert($data);
    }
}
