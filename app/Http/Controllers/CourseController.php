<?php

namespace App\Http\Controllers;

use App\Models\RssCourse;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class CourseController
{
    public function fetchCourses()
    {
        // Use caching to store the parsed data for 10 minutes
        $courses = Cache::remember('courses', 1, function () {
            $client = new Client();
            $response = $client->get('https://git.ir/courses/');
            $html = (string)$response->getBody();
//            dd($html);
            // Use Simple HTML DOM or DOMDocument to parse the HTML
            $dom = new \DOMDocument();
            @$dom->loadHTML($html); // Suppress warnings due to malformed HTML
            $xpath = new \DOMXPath($dom);

            // XPath queries
            $links = $xpath->query("//div[@class='col-sm-12 col-md-6 col-lg-4 my-1']/a/@href");
            $images = $xpath->query("//div[@class='card-img-top ls-is-cached lazyloaded']//img/@src");
//            dd($links);
            $coursesData = [];
            foreach ($links as $index => $link) {
                $courseUrl = $link->nodeValue;
                $imageUrl = $images->item($index)->nodeValue ?? null;

                $coursesData[] = [
                    'url' => $courseUrl,
                    'image_url' => $imageUrl,
                ];
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

        return response()->json($courses); // Return the fetched courses as JSON
    }
}
