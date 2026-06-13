<?php

namespace Tests\Unit\Modules\BaleOtp;

use App\Modules\BaleOtp\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_iranian_mobile_with_leading_zero(): void
    {
        $this->assertSame('989123456789', PhoneNormalizer::normalize('09123456789'));
    }

    public function test_normalizes_ten_digit_mobile_without_zero(): void
    {
        $this->assertSame('989123456789', PhoneNormalizer::normalize('9123456789'));
    }

    public function test_accepts_already_normalized_phone(): void
    {
        $this->assertSame('989123456789', PhoneNormalizer::normalize('989123456789'));
    }

    public function test_rejects_invalid_formats(): void
    {
        $this->assertNull(PhoneNormalizer::normalize('+980912345678'));
        $this->assertNull(PhoneNormalizer::normalize('12345'));
        $this->assertNull(PhoneNormalizer::normalize(''));
        $this->assertNull(PhoneNormalizer::normalize(null));
    }

    public function test_is_valid_helper(): void
    {
        $this->assertTrue(PhoneNormalizer::isValid('09123456789'));
        $this->assertFalse(PhoneNormalizer::isValid('09123'));
    }
}
