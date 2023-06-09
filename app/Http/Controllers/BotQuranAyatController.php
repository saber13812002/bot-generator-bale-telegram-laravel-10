<?php

namespace App\Http\Controllers;

use App\Helpers\LogHelper;
use App\Helpers\QuranHefzBotHelper;
use App\Http\Requests\BotRequest;
use App\Http\Resources\IndexedRecordResource;
use App\Http\Resources\QuranAyatResource;
use App\Models\QuranAyat;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use Saber13812002\Laravel\Fulltext\Search;
use Telegram;

class BotQuranAyatController
{

    /**
     * Display a listing of the resource.
     * @throws GuzzleException
     */
    public function index(BotRequest $request)
    {
        if ($request->has('language')) {
            App::setLocale($request->input('language'));
        } else {
            App::setLocale("fa");
        }

        $type = $request->input('origin');

        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_BALE");
                $bot = new Telegram($token, 'bale');
            } elseif ($request->input('origin') == 'telegram') {
                $token = $request->has('token') ? $request->input('token') : env("QURAN_HEFZ_BOT_TOKEN_TELEGRAM");
                $bot = new Telegram($token);
            } else {
                return 200;
            }

            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            $botText = $bot->Text();

            [$searchPhrase, $pageNumber] = self::getPageNumberFromPhrase($botText);

            QuranHefzBotHelper::findResultThenSend($searchPhrase, $pageNumber, $type, $bot);

            return true;
        }
        return true;
    }


//curl --request POST \
//--url 'http://localhost:8000/api/webhook-quran-word?origin=bale&token=861365999%3Aq11kImnvX9KTrvMJ245zc16TugfixojAT1uzdvke&bot_mother_id=1&language=fa' \
//--header 'Content-Type: application/json' \
//--data '{
//	"update_id": 2,
//	"message": {
//		"message_id": -687281639,
//		"from": {
//			"id": 485750575,
//			"first_name": "صابر طباطبایی یزدی",
//			"username": "sabertaba",
//			"is_bot": false
//		},
//		"date": 1680438429,
//		"chat": {
//			"id": 485750575,
//			"type": "private",
//			"username": "sabertaba",
//			"first_name": "صابر طباطبایی یزدی"
//		},
//		"text": "/1",
//		"forward_from_chat": {
//			"id": 234
//		},
//		"forward_from": {
//			"id": 234
//		}
//	}
//}'
    public function ayat(Response $response, int $id)
    {

        $ayat = QuranAyat::query()->find($id)->first();
//        $aye1 = $ayat["simple"];
        return QuranAyatResource::make($ayat);
    }

    public function search(Response $response, string $phrase)
    {
        $phrase = IndexedRecord::normalize($phrase);

        $search = new Search();
        $results = $search->run($phrase, QuranAyat::class);
//        $firstResult = $results->first()->indexable;
//        dd($firstResult['id'], $firstResult['sura'], $firstResult['aya'], $firstResult['text']);
//        return QuranAyatResource::make($results->first()->indexable);
        return IndexedRecordResource::collection($results);
    }

    public function search2(Response $response, string $phrase)
    {
        $phrase = IndexedRecord::normalize($phrase);
        $results = QuranAyat::query()->where('simple', 'like', $phrase . '%')->paginate();
        return QuranAyatResource::collection($results);
    }

    public function search3(Response $response, string $phrase)
    {

        $phrase = IndexedRecord::normalize($phrase);

        $results = QuranAyat::query()
            ->whereFullText('simple', $phrase . '%')
            ->paginate();

        return QuranAyatResource::collection($results);
    }


}


//curl --request POST \
//  --url 'http://localhost:8000/api/webhook-quran-ayat?origin=bale&token=861365999%3Aq11kImnvX9KTrvMJ245zc16TugfixojAT1uzdvke&bot_mother_id=1&language=fa' \
//  --header 'Content-Type: application/json' \
//  --data '{
//	"update_id": 2,
//	"message": {
//		"message_id": -687281639,
//		"from": {
//			"id": 485750575,
//			"first_name": "صابر طباطبایی یزدی",
//			"username": "sabertaba",
//			"is_bot": false
//		},
//		"date": 1680438429,
//		"chat": {
//			"id": 485750575,
//			"type": "private",
//			"username": "sabertaba",
//			"first_name": "صابر طباطبایی یزدی"
//		},
//		"text": "الم",
//		"forward_from_chat": {
//			"id": 234
//		},
//		"forward_from": {
//			"id": 234
//		}
//	}
//}'
