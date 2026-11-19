<?php

namespace App\Console\Commands;

use App\Models\RssPostItem;
use App\Services\RssPostItemTranslationService;
use App\Services\RssPostItemTranslationToMessengerService;
use Illuminate\Console\Command;

class RssPostToTranslate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rss-post-to-translate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ارسال ترجمه‌های آماده RSS به صف پیام‌رسان‌ها (ترجمه‌های تولیدشده قبلی)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        RssPostItemTranslationToMessengerService::run();
    }
}
