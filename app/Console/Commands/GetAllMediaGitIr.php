<?php

namespace App\Console\Commands;

use App\Models\RssCourse;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GetAllMediaGitIr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-all-media-git-ir';

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
        $courses = Cache::remember('courses', 1000, function () {
            $client = new Client();
            $coursesData = [];

            // Loop through pages 1 to 10
            for ($page = 1; $page <= 10; $page++) {
                $response = $client->get("https://git.ir/courses/?page={$page}");
                $html = (string) $response->getBody();

                // Load the HTML into DOMDocument
                $dom = new \DOMDocument();
                @$dom->loadHTML($html); // Suppress warnings due to malformed HTML
                $xpath = new \DOMXPath($dom);

                // XPath queries
                $links = $xpath->query("//div[@class='col-sm-12 col-md-6 col-lg-4 my-1']/a/@href");
                $imagesDataSrc = $xpath->query("//div[@class='col-sm-12 col-md-6 col-lg-4 my-1']//img/@data-src");
                $imagesSrc = $xpath->query("//div[@class='col-sm-12 col-md-6 col-lg-4 my-1']//img/@src");

                foreach ($links as $index => $link) {
                    $courseUrl = $link->nodeValue;
                    $dataSrc = $imagesDataSrc->item($index)->nodeValue ?? null;
                    $src = $imagesSrc->item($index)->nodeValue ?? null;

                    // Prefer data-src over src
                    $imageUrl = $dataSrc ?: $src;

                    $coursesData[] = [
                        'url' => $courseUrl,
                        'image_url' => $imageUrl,
                    ];
                }
            }

            return $coursesData;
        });

        // Save the courses to the database
        foreach ($courses as $course) {
            RssCourse::updateOrCreate(
                ['url' => $course['url']], // Unique constraint on URL
                ['image_url' => $course['image_url']]
            );
        }
    }
}
