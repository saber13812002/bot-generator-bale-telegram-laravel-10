<?php

namespace App\Http\Controllers;

use App\Models\RssFeedWebOrigin;
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
            $rssFeed .= '<title>' . htmlspecialchars($event['name'] ?? 'No Title') . '</title>';
            $rssFeed .= '<link>https://evand.com/events/' . htmlspecialchars($event['slug'] ?? '') . '</link>';
            $rssFeed .= '<description><![CDATA[';

            // Check for cover image
            if (isset($event['cover']['original'])) {
                $rssFeed .= '<img src="' . htmlspecialchars($event['cover']['original']) . '" alt="' . htmlspecialchars($event['name'] ?? 'Event') . '" /><br/>';
            } else {
                $rssFeed .= '<p>No image available for this event.</p>';
            }

            $rssFeed .= 'City: ' . htmlspecialchars($event['city_name'] ?? 'Unknown') . '<br/>';
            $rssFeed .= 'Organization: ' . htmlspecialchars($event['organization']['data']['name'] ?? 'Unknown') . '<br/>';
            $rssFeed .= 'Start Date: ' . htmlspecialchars($event['start_date'] ?? 'N/A') . '<br/>';
            $rssFeed .= 'End Date: ' . htmlspecialchars($event['end_date'] ?? 'N/A') . '<br/>';
            $rssFeed .= 'Address: ' . htmlspecialchars($event['address'] ?? 'Online') . '<br/>';
            $rssFeed .= ']]></description>';
            $rssFeed .= '<pubDate>' . date(DATE_RSS, strtotime($event['start_date'] ?? 'now')) . '</pubDate>';
            $rssFeed .= '<guid>https://evand.com/events/' . htmlspecialchars($event['slug'] ?? '') . '</guid>';
            $rssFeed .= '</item>';
        }

        $rssFeed .= '</channel>';
        $rssFeed .= '</rss>';

        // Step 3: Return the RSS feed
        return Response::make($rssFeed, 200, [
            'Content-Type' => 'application/rss+xml'
        ]);
    }


    public function audiobook(Request $request)
    {
        $builder = RssFeedWebOrigin::query();
        if ($request->origin) {
            $builder->whereOrigin($request->origin);
        }
        $items = $builder->orderByDesc('id')->limit(40)->get();

        $rssFeed = view('rss.audiobook', compact('items'));

        return Response::make($rssFeed, 200, [
            'Content-Type' => 'application/rss+xml',
        ]);
    }

    public function gitir(Request $request)
    {
        // Fetch the RSS feed
        $rssFeedUrl = 'https://git.ir/feed-fa/';
        $rssContent = file_get_contents($rssFeedUrl);

        // Load the XML
        $xml = simplexml_load_string($rssContent);

        $items = [];
        // Iterate through each item and modify the description
        foreach ($xml->channel->item as $item) {
            $items[] = (object)[
                'title' => (string) $item->title,
                'link' => (string) $item->link,
                'description' => (string) $item->description,
//                'imageUrl' => (string) $item->imageUrl ?? null, // Ensure imageUrl is set correctly
                'imageUrl' => "https://git.ir/media/udemy-img/learn-python-for-data-science-for-complete-beginners.jpg", // Ensure imageUrl is set correctly
                'pubDate' => (string) $item->pubDate
            ];
        }

        return view('rss.gitir', compact('items'));
    }

}
