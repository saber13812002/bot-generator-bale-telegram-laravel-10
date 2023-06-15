<?php

namespace App\Http\Controllers;

use App\Models\QuranAyat;
use Illuminate\Http\Response;

class BotQuranAyatController
{
    public function ayat(Response $response, int $id)
    {

        $ayat = QuranAyat::query()->find($id)->first();
        $aye1 = $ayat["simple"];
        return $aye1;
    }

    public function search(Response $response, string $phrase)
    {

        $search = new \Swis\Laravel\Fulltext\Search();
        $results = $search->run($phrase, QuranAyat::class);
        $firstResult = $results->first()->indexable;
        dd($firstResult['id'], $firstResult['sura'], $firstResult['aya'], $firstResult['text']);
    }
}
