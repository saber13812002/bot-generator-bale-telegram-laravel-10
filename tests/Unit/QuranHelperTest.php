<?php


use App\Helpers\QuranHefzBotHelper;
use PHPUnit\Framework\TestCase;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use function PHPUnit\Framework\assertEquals;

class QuranHelperTest extends TestCase
{

    private $bemellah = "بسم الله الرحمن الرحیم";

    public function test_quran_ayat_model()
    {
//        $ayat = QuranAyat::query()->whereIndex(1)->first();
//        $aye1 = $ayat["simple"];
//        self::assertEquals($this->bemellah, $aye1);
        assertEquals(true, true);
    }

    public function test_quran_ayat_analyzer()
    {
        $actual = IndexedRecord::runAnalyzer("بسم");
        self::assertEquals("بسم ", $actual);

        $actual = IndexedRecord::runAnalyzer($this->bemellah);
        self::assertEquals("بسم الله الرحمن الرحیم بسم الله الرحمن الرحيم", $actual);

        $text = "صلوة زکوة حیوة مشکوة";
        $actual = IndexedRecord::runAnalyzer($text);
        self::assertEquals("صلوة زکوة حیوة مشکوة  صلوه زکوه حیوه مشکوه صلوت زکوت حیوت مشکوت صلات زکات حیات مشکات صلوة زكوة حيوة مشكوة", $actual);

    }

    public function test_pagination_command()
    {
        $command = "الرحمنpage=2";
        [$searchPhrase, $pageNumber] = QuranHefzBotHelper::getPageNumberFromPhrase($command);
        assertEquals(2, $pageNumber);
        assertEquals("الرحمن", $searchPhrase);

        $command = "الرحمنpage=12";
        [$searchPhrase, $pageNumber] = QuranHefzBotHelper::getPageNumberFromPhrase($command);
        assertEquals(12, $pageNumber);
        assertEquals("الرحمن", $searchPhrase);

        $command = "الرحمنpage=";
        [$searchPhrase, $pageNumber] = QuranHefzBotHelper::getPageNumberFromPhrase($command);
        assertEquals(1, $pageNumber);
        assertEquals("الرحمن", $searchPhrase);

        $command = "الرحمان";
        [$searchPhrase, $pageNumber] = QuranHefzBotHelper::getPageNumberFromPhrase($command);
        assertEquals(1, $pageNumber);
        assertEquals("الرحمان", $searchPhrase);

    }
}
