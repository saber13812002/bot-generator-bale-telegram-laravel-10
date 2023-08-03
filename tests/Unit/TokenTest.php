<?php


use App\Helpers\TokenHelper;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{

    public function test_bot_tokens(): void
    {
        $token = env("BOT_MOTHER_TOKEN_BALE", "1234567890:abcdefghijabcdefghijabcdefghijabcdefghij");
        $this->assertTrue(TokenHelper::isToken($token, 'bale'));
        $token = env("BOT_MOTHER_TOKEN_BALE", "1234567890:abcdefghijabcdefghijabcdefghij12345");
        $this->assertTrue(TokenHelper::isToken($token, 'telegram'));
    }

}
