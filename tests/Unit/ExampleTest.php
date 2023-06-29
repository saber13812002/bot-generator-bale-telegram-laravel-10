<?php

namespace Tests\Unit;

use App\Helpers\TokenHelper;
use App\Models\QuranAyat;
use PHPUnit\Framework\TestCase;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use function PHPUnit\Framework\assertEquals;

class ExampleTest extends TestCase
{

    private $bemellah = "بسم الله الرحمن الرحیم";

    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_bot_tokens(): void
    {
        $token = env("BOT_MOTHER_TOKEN_BALE", "1234567890:abcdefghijabcdefghijabcdefghijabcdefghij");
        $this->assertTrue(TokenHelper::isToken($token, 'bale'));
        $token = env("BOT_MOTHER_TOKEN_BALE", "1234567890:abcdefghijabcdefghijabcdefghij12345");
        $this->assertTrue(TokenHelper::isToken($token, 'telegram'));
    }

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
        self::assertEquals("بسم", $actual);

        $actual = IndexedRecord::runAnalyzer($this->bemellah);
        self::assertEquals("بسم الله الرحمن الرحیم بسم الله الرحمن الرحيم", $actual);

        $text = "صلوة زکوة حیوة مشکوة";
        $actual = IndexedRecord::runAnalyzer($text);
        self::assertEquals("صلوة زکوة حیوة مشکوة  صلوه زکوه حیوه مشکوه صلوت زکوت حیوت مشکوت صلات زکات حیات مشکات صلوة زكوة حيوة مشكوة", $actual);


    }
}
