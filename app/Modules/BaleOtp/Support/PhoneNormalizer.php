<?php

namespace App\Modules\BaleOtp\Support;

class PhoneNormalizer
{
    /**
     * Normalize Iranian mobile numbers to Safir format: 989XXXXXXXXX (no leading zero, country code 98).
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            return self::isValidIranianMobile($digits) ? $digits : null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $normalized = '98' . substr($digits, 1);

            return self::isValidIranianMobile($normalized) ? $normalized : null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $normalized = '98' . $digits;

            return self::isValidIranianMobile($normalized) ? $normalized : null;
        }

        return null;
    }

    public static function isValid(?string $phone): bool
    {
        return self::normalize($phone) !== null;
    }

    private static function isValidIranianMobile(string $phone): bool
    {
        return (bool) preg_match('/^989\d{9}$/', $phone);
    }
}
