<?php

namespace App\Modules\BaleOtp\Contracts;

interface BaleOtpSendServiceInterface
{
    /**
     * @return array{balance: int}
     */
    public function sendOtp(string $phone, int $otp): array;
}
