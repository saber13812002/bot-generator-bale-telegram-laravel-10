<?php

namespace Tests\Feature;

use App\Nova\RssItem;
use App\Services\RssItemService;
use Database\Factories\RssItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GetRssItemsThatShouldTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_get_rss_items(): void
    {
        $rssItemsAll = RssItemService::getRssItemsAll();
        $rssItemsAllCount = $rssItemsAll->count();
        $newItem = \App\Models\RssItem::factory()->create();

        $rssItemsAllNew = RssItemService::getRssItemsAll();
        $rssItemsAllNewCount = $rssItemsAllNew->count();


        $this->assertEquals($rssItemsAllCount + 1, $rssItemsAllNewCount);

//        $newItem->last_synced_at =

        $rssItemsThatShouldTest = RssItemService::getRssItemsThatShouldTest();
        $rssItemsThatShouldTestCount = $rssItemsThatShouldTest->count();

//        dd($rssItemsAllCount, $rssItemsAllNewCount, $rssItemsThatShouldTestCount);

    }
}
