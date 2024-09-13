<?php

namespace Tests\Feature;

use App\Models\Nahj;
use Database\Seeders\UploadingMediaFilesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadingMediaFilesTest extends TestCase
{
//    use RefreshDatabase;

    /** @test */
    public function it_retrieves_the_correct_media_url_for_nahj_item()
    {
        // Seed the database with the media files
//        $this->seed(UploadingMediaFilesTableSeeder::class);

        // Get the first Nahj item
        $nahjItem = Nahj::first();

        // Assuming you have a relationship defined in your Nahj model
        $mp3Item = $nahjItem->uploadingMediaFile;

        // Get the media URL
        $mp3ItemMediaUrl = $mp3Item->media_url;

        // Assert that the media URL is correct
        $this->assertEquals('http://farsi.balaghah.net/sites/default/files/temp-image/dashtai/farsi/khotbeh/k1.mp3', $mp3ItemMediaUrl);
    }
}
