<?php

namespace App\Modules\BaleOtp\Contracts;

interface BaleOtpAuthServiceInterface
{
    public function getAccessToken(): string;
}
