<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
//        $this->call(QuranWordsTableSeeder::class);
//        $this->call(QuranSurahsTableSeeder::class);
        $this->call(TranslationsTableSeeder::class);
//        $this->call(TrTransliterationTableSeeder::class);
//        $this->call(EnTransliterationTableSeeder::class);
//        $this->call(QuranTransliterationEnTableSeeder::class);
//        $this->call(QuranTransliterationTrTableSeeder::class);
//        $this->call(QuranTransliterationTrsTableSeeder::class);
        $this->call(RssItemsTableSeeder::class);
        $this->call(RssPostItemsTableSeeder::class);

        $this->call(RssChannelsTableSeeder::class);
        $this->call(RssChannelOriginsTableSeeder::class);
        $this->call(TagsTableSeeder::class);
        $this->call(RssBusinessesTableSeeder::class);
        $this->call(TaggablesTableSeeder::class);
        $this->call(RssPostItemTranslationsTableSeeder::class);
        $this->call(SongsaraMoodsTableSeeder::class);
        $this->call(SharabeBeheshtiMp3sTableSeeder::class);
        $this->call(UploadingMediaFilesTableSeeder::class);
    }
}
