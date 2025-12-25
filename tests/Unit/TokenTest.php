<?php


use App\Helpers\TokenHelper;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{

    public function test_bot_tokens(): void
    {
        // توکن بله با 40 کاراکتر
        $token = "1234567890:abcdefghijabcdefghijabcdefghijabcdefghij";
        $this->assertTrue(TokenHelper::isToken($token, 'bale'));
        
        // توکن بله با 35 کاراکتر (توکن‌های جدید بله)
        $token = "2141755763:Kvzc1ia9lSYXd5mhVYn8rwuz_Jjscch9jcc";
        $this->assertTrue(TokenHelper::isToken($token, 'bale'));
        
        // توکن تلگرام با 35 کاراکتر
        $token = "1234567890:abcdefgh-jabcdefghijabcdefghij12345";
        $this->assertTrue(TokenHelper::isToken($token, 'telegram'));
        
        // توکن نامعتبر (کمتر از 35 کاراکتر)
        $token = "123456789:abcdefgh-jabcdefghijab";
        $this->assertFalse(TokenHelper::isToken($token, 'telegram'));
        $this->assertFalse(TokenHelper::isToken($token, 'bale'));
    }

}
