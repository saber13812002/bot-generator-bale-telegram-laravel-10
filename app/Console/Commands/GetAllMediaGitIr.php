<?php

namespace App\Console\Commands;

use App\Models\RssCourse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GetAllMediaGitIr extends Command
{
    protected $signature = 'app:get-all-media-git-ir';

    protected $description = 'Command description';

    private const HTTP_TIMEOUT = 20;

    public function handle()
    {
        $courses = Cache::remember('courses', 1000, function () {
            try {
                return $this->fetchCoursesFromGitIr();
            } catch (GuzzleException $e) {
                Log::error('GetAllMediaGitIr: connection/timeout to git.ir', ['message' => $e->getMessage()]);
                return [];
            } catch (\Throwable $e) {
                Log::error('GetAllMediaGitIr: error', ['message' => $e->getMessage()]);
                return [];
            }
        });

        foreach ($courses as $course) {
            RssCourse::updateOrCreate(
                ['url' => $course['url']],
                ['image_url' => $course['image_url']]
            );
        }
    }

    private function fetchCoursesFromGitIr(): array
    {
        $client = new Client(['timeout' => self::HTTP_TIMEOUT, 'connect_timeout' => 10]);
        $coursesData = [];

        for ($page = 1; $page <= 2; $page++) {
            $response = $client->get("https://git.ir/courses/?page={$page}");
            $html = (string) $response->getBody();

            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $links = $xpath->query("//div[@class='col-sm-12 col-md-6 col-lg-4 my-1']/a/@href");
            $imagesDataSrc = $xpath->query("//div[@class='col-sm-12 col-md-6 col-lg-4 my-1']//img/@data-src");
            $imagesSrc = $xpath->query("//div[@class='col-sm-12 col-md-6 col-lg-4 my-1']//img/@src");

            foreach ($links as $index => $link) {
                $courseUrl = $link->nodeValue;
                $dataSrc = $imagesDataSrc->item($index)->nodeValue ?? null;
                $src = $imagesSrc->item($index)->nodeValue ?? null;
                $imageUrl = $dataSrc ?: $src;
                $coursesData[] = [
                    'url' => $courseUrl,
                    'image_url' => $imageUrl,
                ];
            }
        }

        return $coursesData;
    }
}
