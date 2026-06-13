<?php

namespace App\Modules\BaleOtp\Exceptions;

use Exception;

class BaleOtpException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?int $errorCode = null,
        public readonly ?int $errorType = null,
    ) {
        parent::__construct($message);
    }

    public static function invalidPhone(): self
    {
        return new self('provided phone number is not valid', 400, 8, 2);
    }

    public static function noBaleAccount(): self
    {
        return new self('this phone does not have an account in Bale', 404, 17, 3);
    }

    public static function rateLimitExceeded(): self
    {
        return new self('rate limit exceeded', 429, 18, 2);
    }

    public static function paymentRequired(): self
    {
        return new self('payment required', 402, 20, 2);
    }

    public static function authFailed(): self
    {
        return new self('Client authentication failed', 401);
    }

    public static function serverError(): self
    {
        return new self('internal server error occurred', 500, 2, 1);
    }
}
