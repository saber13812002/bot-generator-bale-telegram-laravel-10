<?php

namespace Tests\Feature;

use App\Models\QuranAyat;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use Saber13812002\Laravel\Fulltext\Search;
use Tests\TestCase;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertLessThan;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Fulltext search benchmark against the canonical Quran dataset.
     *
     * NOTE: the hardcoded result counts below are only reproducible with the
     * complete Quran dataset (quran_ayats + its laravel_fulltext index) loaded.
     * That dataset is not committed to the repo and does not exist in local
     * dev/test databases, so the test skips (instead of failing) when it is
     * missing. On a machine where the data is loaded, all assertions run.
     */
    public function test_results_count()
    {
        if (QuranAyat::count() === 0) {
            $this->markTestSkipped(
                'Quran dataset (quran_ayats) not loaded in this environment; fulltext counts cannot be verified.'
            );
        }

        //place this before any script you want to calculate time
        $time_start = microtime(true);

//sample script


        $botText = "القرية";
        assertEquals(10, $this->countResultSearchQuran($botText));

        $botText = "الْقَرْيَتَ";
        assertEquals(1, $this->countResultSearchQuran($botText));

        $botText = "قرية";
        assertEquals(23, $this->countResultSearchQuran($botText));

        $botText = "وَاسأَلِ";
        assertEquals(5, $this->countResultSearchQuran($botText));

        $botText = "صوم";
        assertEquals(2, $this->countResultSearchQuran($botText));

        $botText = "صِيامٍ";
        assertEquals(2, $this->countResultSearchQuran($botText));

        $botText = "فصیام";
        assertEquals(4, $this->countResultSearchQuran($botText));

        $botText = "محمد";
        assertEquals(4, $this->countResultSearchQuran($botText));

        $botText = "الارض";
        assertEquals(0, $this->countResultSearchQuran($botText));

        $botText = "الأرض";
        assertEquals(0, $this->countResultSearchQuran($botText));

        $time_end = microtime(true);

//dividing with 60 will give the execution time in minutes otherwise seconds
        $execution_time = ($time_end - $time_start) * 1000;

//execution time of the script
        assertLessThan(3500, $execution_time);


    }

    /**
     * @param $botText
     * @return int
     */
    public function countResultSearchQuran($botText): int
    {
        $botText = IndexedRecord::normalize($botText);
        $search = new Search();
        $results = $search->run($botText, QuranAyat::class);
        return $results->count();
    }
}
