<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScrapSongsara extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrap-songsara {page=1}';

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

        $baseUrl = 'https://songsara.net/mood/happy/page/';
        $page = $this->argument('page');

        $url = $baseUrl . $page;
        $this->info("Scraping: $url");

        try {
            $html = file_get_contents($url);
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);

            // Query for post links
            $postLinks = $xpath->query('//section[@class="posting"]//a[@class="post-img-hover"]/@href');

            $uniqueLinks = [];
            $this->info($postLinks->count());
            foreach ($postLinks as $link) {
                dd($link);
                $audioUrl = $link->nodeValue; // Get the href attribute
                if (!in_array($audioUrl, $uniqueLinks)) {
                    $uniqueLinks[] = $audioUrl;

                    // Save to songsara_posts table
                    DB::table('songsara_posts')->updateOrInsert(
                        ['link' => $audioUrl], // Unique key
                        [
                            'title' => '', // You can extract titles if needed
                            'description' => '', // You can extract descriptions if needed
                            'pub_date' => now(),
                        ]
                    );
                }
            }

            $this->info('Scraping completed! Unique links saved: ' . count($uniqueLinks));

        } catch (\Exception $e) {
            $this->error("Failed to scrape $url: " . $e->getMessage());
        }

    }
}
