<?php

namespace App\Modules\BaleOtp\Services;

use App\Modules\BaleOtp\Contracts\BaleOtpAuthServiceInterface;
use App\Modules\BaleOtp\Contracts\BaleOtpSendServiceInterface;
use App\Modules\BaleOtp\Exceptions\BaleOtpException;
use App\Modules\BaleOtp\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaleOtpSendService implements BaleOtpSendServiceInterface
{
    public function __construct(
        private readonly BaleOtpAuthServiceInterface $authService,
    ) {
    }

    public function sendOtp(string $phone, int $otp): array
    {
        $normalizedPhone = PhoneNormalizer::normalize($phone);
        if ($normalizedPhone === null) {
            throw BaleOtpException::invalidPhone();
        }

        $token = $this->authService->getAccessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl() . '/send_otp', [
                'phone' => $normalizedPhone,
                'otp' => $otp,
            ]);

        if ($response->status() === 400) {
            throw $this->mapErrorResponse($response->json(), 400) ?? BaleOtpException::invalidPhone();
        }

        if ($response->status() === 404) {
            throw BaleOtpException::noBaleAccount();
        }

        if ($response->status() === 402) {
            throw BaleOtpException::paymentRequired();
        }

        if ($response->status() === 429) {
            throw BaleOtpException::rateLimitExceeded();
        }

        if (!$response->successful()) {
            Log::error('Bale Safir send_otp failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'phone' => $normalizedPhone,
            ]);
            throw BaleOtpException::serverError();
        }

        $data = $response->json();

        return [
            'balance' => (int) ($data['balance'] ?? 0),
        ];
    }

    private function mapErrorResponse(?array $data, int $status): ?BaleOtpException
    {
        if (!is_array($data)) {
            return null;
        }

        $code = (int) ($data['code'] ?? 0);
        $message = (string) ($data['message'] ?? 'unknown error');

        return match ($code) {
            8 => BaleOtpException::invalidPhone(),
            17 => BaleOtpException::noBaleAccount(),
            18 => BaleOtpException::rateLimitExceeded(),
            20 => BaleOtpException::paymentRequired(),
            default => new BaleOtpException($message, $status, $code, (int) ($data['type'] ?? 0)),
        };
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('bale-otp.base_url'), '/');
    }
}
