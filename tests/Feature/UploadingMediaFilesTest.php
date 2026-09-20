<?php

namespace Tests\Feature;

use App\Models\Nahj;
use App\Models\UploadingMediaFile;
use Tests\TestCase;

class UploadingMediaFilesTest extends TestCase
{
    private ?Nahj $nahjItem = null;

    protected function tearDown(): void
    {
        // Clean up the rows this test creates so it stays isolated and
        // repeatable (the production dataset that used to feed this test is
        // not committed to the repo).
        if ($this->nahjItem) {
            UploadingMediaFile::where('model_type', 'App/Model/Nahj')
                ->where('model_id', $this->nahjItem->id)
                ->delete();
            $this->nahjItem->forceDelete();
        }

        parent::tearDown();
    }

    /** @test */
    public function it_retrieves_the_correct_media_url_for_nahj_item()
    {
        // Seed the rows this test needs: one Nahj item and the media file
        // (mp3) that references it via the morph-to-less hasOne relation.
        // NOTE: UploadingMediaFilesTableSeeder exists but was never run in
        // test/dev DBs (the seeder call below it was commented out and the
        // dataset is not committed), so we create the rows explicitly.
        $this->nahjItem = Nahj::forceCreate([
            'category_id' => 1,
            'number' => 1,
            'title' => 'خطبه 1 نهج البلاغه',
            'persian' => 'متن فارسی خطبه اول',
        ]);

        UploadingMediaFile::forceCreate([
            'title' => 'خطبه 1 نهج البلاغه',
            'model_type' => 'App/Model/Nahj',
            'model_id' => $this->nahjItem->id,
            'media_url' => 'http://farsi.balaghah.net/sites/default/files/temp-image/dashtai/farsi/khotbeh/k1.mp3',
            'media_type' => 'mp3',
        ]);

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
