<?php

namespace App\Repositories;

use App\Helpers\StringHelper;
use App\Interfaces\Repositories\HadithApiRepository;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class HadithApiRepositoryImpl implements HadithApiRepository
{

    /**
     * @throws GuzzleException
     * @throws RequestException
     */
    public function call(string $phrase, string $currentPage, string $pageSize)
    {
        return self::callHadithAcademyOfIslam($phrase, $currentPage, $pageSize);
    }


    /**
     * @throws GuzzleException
     * @throws RequestException
     */
    private static function callHadithAcademyOfIslam(string $phrase, string $currentPage, string $pageSize)
    {
//        $api_key = env("HADITH_API_TOKEN");
//        $client = new GuzzleHttp\Client();
        $baseUrl = env("APP_ENV") != "local" ? "https://hadith.academyofislam.com" : "http://localhost:3000";
        $uri = $baseUrl . '/v1/narrations' . '?q=' . StringHelper::normalizer($phrase) . '&page=' . $currentPage . '&per_page=' . $pageSize;
//        dd($uri);
        $response = Http::get($uri)->throw();


//        $response = $client->get($uri);
//        echo $request->getStatusCode(); // 200
//        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
    }
}
