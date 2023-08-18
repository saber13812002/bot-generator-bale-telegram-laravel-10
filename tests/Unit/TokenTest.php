<?php


use App\Helpers\TokenHelper;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{

    public function test_bot_tokens(): void
    {
        $token = "1234567890:abcdefghijabcdefghijabcdefghijabcdefghij";
        $this->assertTrue(TokenHelper::isToken($token, 'bale'));
        $token = "1234567890:abcdefgh-jabcdefghijabcdefghij12345";
        $this->assertFalse(TokenHelper::isToken($token, 'bale'));
        $this->assertTrue(TokenHelper::isToken($token, 'telegram'));
        $token = "123456789:abcdefgh-jabcdefghijab";
        $this->assertFalse(TokenHelper::isToken($token, 'telegram'));
    }

}
