<?php

namespace App\Repositories;

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
        $baseUrl = env("APP_ENV") != "local" 
            ? "https://hadith.academyofislam.com" 
            : "http://localhost:3000";
        
        // استفاده از query parameters که Laravel خودش encoding را انجام می‌دهد
        $response = Http::get($baseUrl . '/v1/narrations', [
            'q' => $phrase,  // Laravel خودش urlencode می‌کند
            'page' => $currentPage,
            'per_page' => $pageSize
        ])->throw();
        
        return $response->json();
    }
}
