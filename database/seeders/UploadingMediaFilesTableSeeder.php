<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UploadingMediaFilesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        // Clear the table
        \DB::table('uploading_media_files')->delete();

        $data = [];
        for ($i = 1; $i <= 241; $i++) {
            $data[] = [
                'id' => $i,
                'title' => "خطبه {$i} نهج البلاغه",
                'model_type' => 'App/Model/Nahj',
                'model_id' => $i,
                'media_url' => "http://farsi.balaghah.net/sites/default/files/temp-image/dashtai/farsi/khotbeh/k{$i}.mp3",
                'media_type' => 'mp3',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \DB::table('uploading_media_files')->insert($data);
    }
}
