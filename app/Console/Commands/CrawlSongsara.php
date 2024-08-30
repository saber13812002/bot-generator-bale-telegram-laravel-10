<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlSongsara extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crawl-songsara';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl songsara.net for audio sources';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $moodLinks = [];
        $baseUrl = 'https://songsara.net';

        $moods = [
            'spring' => 'بهاری',
            'relaxing' => 'آرامش بخش',
            'happy' => 'شاد',
            'epic' => 'حماسی',
            'backgroundmusic' => 'تدوین فیلم',
            'chill' => 'چیل',
            'no-copyright' => 'بدون کپی رایت',
            'positive-energy' => 'انرژی مثبت',
            'depressed' => 'غمگین',
            'focus' => 'تمرکز',
            'romantic' => 'عاشقانه',
            'study' => 'مطالعه',
            'excitedly' => 'هیجانی',
            'contemplative' => 'تامل برانگیز',
            'sport' => 'ورزشی',
            'dramatic' => 'دراماتیک',
            'dreamy' => 'رویایی',
            'sentimental' => 'احساسی',
            'rainy' => 'بارونی',
            'phonetic' => 'آوایی',
            'imagination' => 'خیال پردازی',
            'baby-sleep' => 'خواب کودک',
            'sad-piano' => 'پیانو غمگین',
            'orchestral' => 'ارکسترال',
            'hopeful' => 'امید بخش',
            'trailer' => 'تریلر',
            'relax-guitar' => 'گیتار آرامبخش',
            'night' => 'شب',
            'sleep' => 'خواب',
            'piano-solo' => 'تکنوازی پیانو',
            'summer' => 'تابستان',
            'autumn' => 'پاییز',
            'winter' => 'زمستان'
        ];

        foreach ($moods as $mood => $value) {
            $url = $baseUrl. "/mood/" . $mood;
            $this->info("Crawling: $url");

            try {
                $this->crawl($url);

                // Save mood link to song_mood table
                DB::table('songsara_moods')->insert([
                    'name' => $mood,
                    'slug' => $mood,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            } catch (\Exception $e) {
                $this->error("Failed to crawl $url: " . $e->getMessage());
                continue; // Skip to the next mood
            }
        }

        $this->info('Crawling completed!');

    }

    /**
     * @param string $url
     * @return void
     */
    public function crawl(string $url): void
    {
        $html = file_get_contents($url);

        // Create a new DOMDocument and load HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML($html); // Use @ to suppress warnings for malformed HTML

        // Create a new DOMXPath object
        $xpath = new \DOMXPath($dom);

        // Query for audio sources
        $audioSources = $xpath->query('//div[@class="audioplayer-source"]');

        foreach ($audioSources as $source) {
            $audioUrl = $source->getAttribute('data-src');
            $title = $source->parentNode->getAttribute('data-title'); // Adjust as per structure
            $description = $source->parentNode->getAttribute('data-info'); // Adjust as per structure

            // Save to songsara_posts table
            DB::table('songsara_posts')->insert([
                'title' => $title,
                'link' => $audioUrl,
                'description' => $description,
                'pub_date' => now(),
            ]);
        }
    }
}
