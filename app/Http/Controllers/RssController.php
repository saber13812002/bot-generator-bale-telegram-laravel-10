<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;

class RssController extends Controller
{
    public function generateRSS()
    {
        // Step 1: Fetch data from the API
        $response = Http::get('https://api.evand.com/v2/events', [
            'sort' => 'trending',
            'per_page' => 11,
            'include' => 'city,organization,prices',
            'fields' => 'city_id,organization_id,name,slug,start_date,end_date,cover,online,ended,soldout,address',
        ]);

        $events = $response->json()['data'];

        // Step 2: Transform the data into RSS format
        $rssFeed = '<?xml version="1.0" encoding="UTF-8" ?>';
        $rssFeed .= '<rss version="2.0">';
        $rssFeed .= '<channel>';
        $rssFeed .= '<title>Evand Events</title>';
        $rssFeed .= '<link>https://evand.com/</link>';
        $rssFeed .= '<description>Trending events from Evand</description>';
        $rssFeed .= '<language>fa-ir</language>';

        foreach ($events as $event) {
            $rssFeed .= '<item>';
            $rssFeed .= '<title>' . htmlspecialchars($event['name']) . '</title>';
            $rssFeed .= '<link>https://evand.com/events/' . htmlspecialchars($event['slug']) . '</link>';
            $rssFeed .= '<description><![CDATA[';
            $rssFeed .= '<img src="' . htmlspecialchars($event['cover']['original']) . '" alt="' . htmlspecialchars($event['name']) . '" /><br/>';
            $rssFeed .= 'City: ' . htmlspecialchars($event['city_name']) . '<br/>';
            $rssFeed .= 'Organization: ' . htmlspecialchars($event['organization']['data']['name']) . '<br/>';
            $rssFeed .= 'Start Date: ' . htmlspecialchars($event['start_date']) . '<br/>';
            $rssFeed .= 'End Date: ' . htmlspecialchars($event['end_date']) . '<br/>';
            $rssFeed .= 'Address: ' . htmlspecialchars($event['address'] ?? 'Online') . '<br/>';
            $rssFeed .= ']]></description>';
            $rssFeed .= '<pubDate>' . date(DATE_RSS, strtotime($event['start_date'])) . '</pubDate>';
            $rssFeed .= '<guid>https://evand.com/events/' . htmlspecialchars($event['slug']) . '</guid>';
            $rssFeed .= '</item>';
        }

        $rssFeed .= '</channel>';
        $rssFeed .= '</rss>';

        // Step 3: Return the RSS feed
        return Response::make($rssFeed, 200, [
            'Content-Type' => 'application/rss+xml'
        ]);
    }
}
