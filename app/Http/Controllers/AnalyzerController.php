<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Saber13812002\Laravel\Fulltext\IndexedRecord;

class AnalyzerController
{
    public function testAnalyzer(Response $response, string $phrase)
    {
        $results = IndexedRecord::normalize($phrase);

        return $results;
    }
}
