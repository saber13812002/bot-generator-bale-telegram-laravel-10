<?php

namespace App\Http\Controllers;

use App\Http\Resources\IndexedRecordResource;
use App\Http\Resources\QuranAyatResource;
use App\Models\QuranAyat;
use Illuminate\Http\Response;

class BotQuranAyatController
{
    public function ayat(Response $response, int $id)
    {

        $ayat = QuranAyat::query()->find($id)->first();
//        $aye1 = $ayat["simple"];
        return QuranAyatResource::make($ayat);
    }

    public function search(Response $response, string $phrase)
    {
        $search = new \Swis\Laravel\Fulltext\Search();
        $results = $search->run($phrase, QuranAyat::class);
        $firstResult = $results->first()->indexable;
//        dd($firstResult['id'], $firstResult['sura'], $firstResult['aya'], $firstResult['text']);
//        return QuranAyatResource::make($results->first()->indexable);
        return IndexedRecordResource::collection($results);
    }

    public function search2(Response $response, string $phrase)
    {
        $results = QuranAyat::query()->where('simple', 'like',  $phrase . '%')->paginate();
        return QuranAyatResource::collection($results);
    }
}
