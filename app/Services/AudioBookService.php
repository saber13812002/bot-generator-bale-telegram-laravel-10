<?php

namespace App\Services;

use App\Models\RssFeedWebOrigin;
use Illuminate\Support\Facades\Http;

class AudioBookService
{

    public static function getAudioBookDetails($audioBookId)
    {
        $response = self::callDetailApi($audioBookId);

        // Check if the response is successful
        if ($response->successful()) {
            $responseData = self::saveAndGetResponseData($response, $audioBookId);

// Return the JSON response
            return response()->json($responseData);
        } else {
            // Handle errors
            return [
                'error' => 'Unable to fetch audio book details',
                'status' => $response->status(),
            ];
        }
    }

    /**
     * @param $audioBookId
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response
     */
    public static function callDetailApi($audioBookId): \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
    {
// Construct the URL
        $url = "https://www.navaar.ir/api/audiobooks/{$audioBookId}/detail2";

        // Make the API request
        $response = Http::withHeaders([
            'cookie' => 'ASP.NET_SessionId=ha2sa5midtdgse0nbz2nt2y4; rocketchatscreenshare=chrome; analytics_token=8c47f662-a58c-ac35-841f-77e5d3820bde; _yngt_iframe=1; _ga=GA1.1.618643529.1721939419; _yngt=52bc8431-1b2f5-5020b-b1e9d-d2d440436450; .AspNet.ApplicationCookie=_836x8yH1R22y0xo_bvWG28D8s70Mp9S2J_8pUVguWAtwZD5TcPvWKf3qFKDWljY0uah0fzhXYWjr33zIILBVrxd_Syq4AjALALnymTRgiwbLd3TzslNBW2BA1TVlcQVysDt1_SrAdYyEju29wtZzCuSe2zq23xKO6JEh0p0KJYBPgw_EciC4Wl7jcjApD19fStFUVDueuNFCyaiA_j37afuPl0m3QtbWJCgWqOs7qWCl0nBx9NaqNlHqiqty3J8ovMl7cF-62eedy6EkxXgVwBjKNpHDA_olby0nncVtMcONp4xQ7QQdzIXi7sEKYtq-J4T44j_Wt2gXLUVzPvSl6-dfXDTXXwXObRXM2yYSxtY11n1GoGfAEL-fwulkb5HCmNDnoXKlG40wluy4rTiTEKaMKzAQEyD5USb1qxVIGjjzQpz44rL3PvJkTJtUYDoleHevF9P6jnPhS629WDc1_ch36zNvOTcPXXVbCSK1lGWmNhBGq3VmA0lWA9wOq1h; _ga_YM6GH7TVNE=GS1.1.1722605165.1.1.1722606101.60.0.0; _ga_QYRYYNHX0M=deleted; yektanet_session_last_activity=8/6/2024; _ga_QYRYYNHX0M=deleted; _clck=1bbn1c9|2|fo3|0|1667; analytics_campaign={"source":"web.splus.ir","medium":"referral"}; analytics_session_token=eea5ff41-cff2-dd51-e05b-8ed28fc6cd9a; _ga_QYRYYNHX0M=GS1.1.1722971905.16.1.1722976002.60.0.0',
            'accept' => 'application/json, text/plain, */*',
            'api-level' => '1',
            'client-id' => '3',
            'device-id' => '23ea619f-0ffe-102c-3825-083604bc70f9',
        ])->get($url);
        return $response;
    }

    /**
     * @param \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response $response
     * @param $audioBookId
     * @return string[]
     */
    public static function saveAndGetResponseData(\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response $response, $audioBookId): array
    {
// Assuming $response is already defined and contains the necessary data
        $data = $response->json();

        RssFeedWebOrigin::where('media_id', $audioBookId)
            ->update([
                'audio_book_id' => $data['audioBookId'], // Use the appropriate key from the response
                'details' => $data, // Store the entire JSON response
            ]);

        // Construct the OGG file URL
// Construct the file URL
        $fileUrl = "https://www.navaar.ir/content/books/{$data['audioBookId']}/sample.ogg";
        //localhost:8000/api/audiobooks/19875
// Prepare your response data
        $responseData = [
            'file_url' => $fileUrl,
            'message' => 'Audio file is ready for download.',
            // Add any other data you want to include in the response
        ];
        return $responseData;
    }

    public static function saveAudioBookUUIdWithMediaId($mediaId, $audioBookUuid): void
    {
        RssFeedWebOrigin::where('media_id', $mediaId)
            ->update([
                'audio_book_id' => $audioBookUuid
            ]);
    }
}
