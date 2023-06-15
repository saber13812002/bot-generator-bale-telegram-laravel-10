<?php

namespace Tests\Unit;

use App\Helpers\TokenHelper;
use App\Models\QuranAyat;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
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
//        self::assertEquals("بسم الله الرحمن الرحیم", $aye1);

    }
}
