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
        

        \DB::table('uploading_media_files')->delete();
        
        \DB::table('uploading_media_files')->insert(array (
            0 => 
            array (
                'id' => 1,
                'title' => 'خطبه ۱ نهج البلاغه',
                'model_type' => 'App/Model/Nahj',
                'model_id' => 1,
                'media_url' => 'http://farsi.balaghah.net/sites/default/files/temp-image/dashtai/farsi/khotbeh/k1.mp3',
                'media_type' => 'mp3',
                'created_at' => '2024-09-13 18:49:11',
                'updated_at' => '2024-09-13 18:49:11',
            ),
            1 => 
            array (
                'id' => 2,
                'title' => 'خطبه ۲ نهج البلاغه',
                'model_type' => 'App/Model/Nahj',
                'model_id' => 2,
                'media_url' => 'http://farsi.balaghah.net/sites/default/files/temp-image/dashtai/farsi/khotbeh/k2.mp3',
                'media_type' => 'mp3',
                'created_at' => '2024-09-13 18:49:11',
                'updated_at' => '2024-09-13 18:49:11',
            ),
            2 => 
            array (
                'id' => 3,
                'title' => 'خطبه ۳ نهج البلاغه',
                'model_type' => 'App/Model/Nahj',
                'model_id' => 3,
                'media_url' => 'http://farsi.balaghah.net/sites/default/files/temp-image/dashtai/farsi/khotbeh/k3.mp3',
                'media_type' => 'mp3',
                'created_at' => '2024-09-13 18:49:11',
                'updated_at' => '2024-09-13 18:49:11',
            ),
        ));
        
        
    }
}