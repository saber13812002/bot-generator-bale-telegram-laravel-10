<?php

namespace App\Modules\BaleOtp\Services;

use App\Modules\BaleOtp\Contracts\BaleOtpAuthServiceInterface;
use App\Modules\BaleOtp\Exceptions\BaleOtpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaleOtpAuthService implements BaleOtpAuthServiceInterface
{
    public function getAccessToken(): string
    {
        $cacheKey = config('bale-otp.token_cache_key', 'bale_safir_access_token');
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = config('bale-otp.client_id');
        $clientSecret = config('bale-otp.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            throw BaleOtpException::authFailed();
        }

        $response = Http::asForm()->post($this->baseUrl() . '/auth/token', [
            'grant_type' => 'client_credentials',
            'client_secret' => $clientSecret,
            'scope' => 'read',
            'client_id' => $clientId,
        ]);

        if ($response->status() === 401) {
            throw BaleOtpException::authFailed();
        }

        if (!$response->successful()) {
            Log::error('Bale Safir auth failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw BaleOtpException::serverError();
        }

        $data = $response->json();
        $token = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        if (!is_string($token) || $token === '') {
            throw BaleOtpException::serverError();
        }

        Cache::put($cacheKey, $token, max(60, $expiresIn - 60));

        return $token;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('bale-otp.base_url'), '/');
    }
}
