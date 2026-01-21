<?php

namespace Database\Seeders;

use App\Models\EmailThresholdSetting;
use Illuminate\Database\Seeder;

class EmailThresholdSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'threshold_type' => 'daily',
                'max_emails' => 100,
                'current_count' => 0,
                'reset_at' => now(),
            ],
            [
                'threshold_type' => 'weekly',
                'max_emails' => 500,
                'current_count' => 0,
                'reset_at' => now(),
            ],
            [
                'threshold_type' => 'monthly',
                'max_emails' => 2000,
                'current_count' => 0,
                'reset_at' => now(),
            ],
        ];

        foreach ($settings as $setting) {
            EmailThresholdSetting::updateOrCreate(
                ['threshold_type' => $setting['threshold_type']],
                $setting
            );
        }

        $this->command->info('✅ Email Threshold Settings seeded successfully');
    }
}
