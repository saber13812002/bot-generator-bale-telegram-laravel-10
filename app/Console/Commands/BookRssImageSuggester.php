<?php

namespace App\Console\Commands;

use App\Models\RssPostItem;
use App\Services\AudioBookService;
use Illuminate\Console\Command;

class BookRssImageSuggester extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:book-rss-image-suggester';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rssPostItemNoImage = RssPostItem::orderByDesc('id')
            ->whereNull('image_url')
            ->whereRssItemId(42)
            ->first();
//        dd($rssPostItemNoImage);
        $media_id = AudioBookService::convertLinkToMediaId($rssPostItemNoImage->link);
//        dd($media_id);
        // calc image url
        $uuid = AudioBookService::getUuidByMediaId($media_id);
//        dd($uuid);
//        $uuid = "2897df66-797b-4e1c-b25c-64fb1708fbcc";
        $image_url = "https://www.navaar.ir/content/books/" . $uuid . "/pic.jpg?w=370&h=370&t=AAAAAB9ANMQ=&mode=stretch";

        // save
        $rssPostItemNoImage->image_url = $image_url;
        $rssPostItemNoImage->save();
    }
}
