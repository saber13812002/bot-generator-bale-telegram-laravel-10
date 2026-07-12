<?php

namespace App\Services;

use App\Models\BotUsers;
use App\Modules\BaleOtp\Contracts\BaleOtpSendServiceInterface;
use App\Modules\BaleOtp\Exceptions\BaleOtpException;
use App\Modules\BaleOtp\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class MawkibFinderOtpService
{
    public function __construct(
        private readonly BaleOtpSendServiceInterface $baleOtpSendService,
    ) {}

    public function sendOtp(BotUsers $botUser, string $phone): array
    {
        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized === null) {
            return ['success' => false, 'message' => trans('bot.mawkib_finder_invalid_phone')];
        }

        $otpLength = (int) config('bale-otp.otp_length', 6);
        $otp = random_int((int) str_pad('1', $otpLength, '0'), (int) str_pad('9', $otpLength, '9'));
        $ttl = (int) config('bale-otp.otp_ttl', 300);

        try {
            $this->baleOtpSendService->sendOtp($normalized, $otp);
        } catch (BaleOtpException $e) {
            Log::warning('[MawkibFinder] OTP send failed', [
                'phone' => $normalized,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $this->mapOtpExceptionMessage($e)];
        }

        $botUser->settings([
            'mawkib_otp_phone' => $normalized,
            'mawkib_otp_hash' => Hash::make((string) $otp),
            'mawkib_otp_expires_at' => Carbon::now()->addSeconds($ttl)->toIso8601String(),
            'mawkib_otp_attempts' => 0,
        ]);

        return ['success' => true, 'message' => trans('bot.mawkib_finder_otp_sent')];
    }

    public function verifyOtp(BotUsers $botUser, string $otp): array
    {
        $phone = $botUser->setting('mawkib_otp_phone');
        $hash = $botUser->setting('mawkib_otp_hash');
        $expiresAt = $botUser->setting('mawkib_otp_expires_at');

        if (!$phone || !$hash || !$expiresAt) {
            return ['success' => false, 'message' => trans('bot.mawkib_finder_otp_not_found')];
        }

        if (Carbon::parse($expiresAt)->isPast()) {
            $this->clearOtpSession($botUser);

            return ['success' => false, 'message' => trans('bot.mawkib_finder_otp_expired')];
        }

        $attempts = (int) $botUser->setting('mawkib_otp_attempts', 0);
        $maxAttempts = (int) config('bale-otp.max_attempts', 5);

        if ($attempts >= $maxAttempts) {
            $this->clearOtpSession($botUser);

            return ['success' => false, 'message' => trans('bot.mawkib_finder_otp_max_attempts')];
        }

        $botUser->settings(['mawkib_otp_attempts' => $attempts + 1]);

        if (!Hash::check(trim($otp), $hash)) {
            return ['success' => false, 'message' => trans('bot.mawkib_finder_otp_invalid')];
        }

        $this->clearOtpSession($botUser);
        $botUser->settings([
            'mawkib_verified_phone' => $phone,
        ]);

        return ['success' => true, 'phone' => $phone];
    }

    public function clearOtpSession(BotUsers $botUser): void
    {
        $botUser->settings([
            'mawkib_otp_phone' => null,
            'mawkib_otp_hash' => null,
            'mawkib_otp_expires_at' => null,
            'mawkib_otp_attempts' => null,
        ]);
    }

    private function mapOtpExceptionMessage(BaleOtpException $e): string
    {
        return match (true) {
            $e->httpStatus === 404 || str_contains($e->getMessage(), 'does not have an account') => trans('bot.mawkib_finder_no_bale_account'),
            str_contains($e->getMessage(), 'rate limit') => trans('bot.mawkib_finder_otp_rate_limit'),
            str_contains($e->getMessage(), 'payment required') => trans('bot.mawkib_finder_otp_payment_required'),
            str_contains($e->getMessage(), 'not valid') => trans('bot.mawkib_finder_invalid_phone'),
            default => trans('bot.mawkib_finder_otp_send_failed'),
        };
    }
}
