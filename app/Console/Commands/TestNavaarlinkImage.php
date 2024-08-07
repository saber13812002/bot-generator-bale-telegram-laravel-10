<?php

namespace App\Console\Commands;

use App\Services\RssService;
use Illuminate\Console\Command;

class TestNavaarlinkImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-navaarlink-image';

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
        $imageUrl = "https://www.navaar.ir/content/books/7120bf19-ea37-4a8d-831a-71024077521b/pic.jpg?w=370&h=370&t=AAAAAB9AN10=&mode=stretch";
        $link = "https://www.navaar.ir/audiobook/12266";

        if (strpos($imageUrl, 'navaar.ir') !== false) {
            RssService::getAndSaveMediaIdIfNavaar($link, $imageUrl);
        }
    }
}
