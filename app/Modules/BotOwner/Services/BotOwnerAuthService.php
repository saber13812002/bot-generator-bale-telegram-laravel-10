<?php

namespace App\Modules\BotOwner\Services;

use App\Modules\BaleOtp\Contracts\BaleOtpSendServiceInterface;
use App\Modules\BaleOtp\Exceptions\BaleOtpException;
use App\Modules\BaleOtp\Support\PhoneNormalizer;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerOtpSessionRepositoryInterface;
use App\Modules\BotOwner\Contracts\BotOwnerRepositoryInterface;
use App\Modules\BotOwner\Models\BotOwner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class BotOwnerAuthService implements BotOwnerAuthServiceInterface
{
    public const SESSION_KEY = 'bot_owner_id';

    public function __construct(
        private readonly BotOwnerRepositoryInterface $ownerRepository,
        private readonly BotOwnerOtpSessionRepositoryInterface $otpSessionRepository,
        private readonly BaleOtpSendServiceInterface $baleOtpSendService,
    ) {
    }

    public function sendOtp(string $phone, ?string $ip = null): array
    {
        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized === null) {
            return ['success' => false, 'message' => trans('bot-owner.invalid_phone')];
        }

        $rateLimit = (int) config('bale-otp.rate_limit_per_hour', 30);
        if ($this->otpSessionRepository->countRecentForPhone($normalized) >= $rateLimit) {
            return ['success' => false, 'message' => trans('bot-owner.rate_limit_exceeded')];
        }

        $otpLength = (int) config('bale-otp.otp_length', 6);
        $otp = random_int((int) str_pad('1', $otpLength, '0'), (int) str_pad('9', $otpLength, '9'));
        $ttl = (int) config('bale-otp.otp_ttl', 300);

        try {
            $this->baleOtpSendService->sendOtp($normalized, $otp);
        } catch (BaleOtpException $e) {
            Log::warning('BotOwner OTP send failed', [
                'phone' => $normalized,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $this->mapOtpExceptionMessage($e)];
        }

        $this->otpSessionRepository->createSession(
            $normalized,
            Hash::make((string) $otp),
            Carbon::now()->addSeconds($ttl),
            $ip
        );

        return ['success' => true, 'message' => trans('bot-owner.otp_sent')];
    }

    public function verifyOtp(string $phone, string $otp): array
    {
        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized === null) {
            return ['success' => false, 'message' => trans('bot-owner.invalid_phone')];
        }

        $session = $this->otpSessionRepository->findLatestForPhone($normalized);
        if ($session === null) {
            return ['success' => false, 'message' => trans('bot-owner.otp_not_found')];
        }

        if ($session->isExpired()) {
            $this->otpSessionRepository->deleteForPhone($normalized);

            return ['success' => false, 'message' => trans('bot-owner.otp_expired')];
        }

        $maxAttempts = (int) config('bale-otp.max_attempts', 5);
        if ($session->attempts >= $maxAttempts) {
            $this->otpSessionRepository->deleteForPhone($normalized);

            return ['success' => false, 'message' => trans('bot-owner.otp_max_attempts')];
        }

        $session->increment('attempts');

        if (!Hash::check(trim($otp), $session->otp_hash)) {
            return ['success' => false, 'message' => trans('bot-owner.otp_invalid')];
        }

        $this->otpSessionRepository->deleteForPhone($normalized);

        $owner = $this->ownerRepository->createOrUpdateByPhone($normalized, [
            'last_login_at' => now(),
            'status' => 'active',
        ]);

        $this->loginSession($owner);

        return [
            'success' => true,
            'message' => trans('bot-owner.login_success'),
            'bot_owner' => $owner,
        ];
    }

    public function loginSession(BotOwner $owner): void
    {
        Session::put(self::SESSION_KEY, $owner->id);
        Session::regenerate();
    }

    public function logoutSession(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::regenerate();
    }

    public function currentOwner(): ?BotOwner
    {
        $id = Session::get(self::SESSION_KEY);
        if (!$id) {
            return null;
        }

        return $this->ownerRepository->findById((int) $id);
    }

    private function mapOtpExceptionMessage(BaleOtpException $e): string
    {
        return match (true) {
            $e->httpStatus === 404 || str_contains($e->getMessage(), 'does not have an account') => trans('bot-owner.no_bale_account'),
            str_contains($e->getMessage(), 'rate limit') => trans('bot-owner.rate_limit_exceeded'),
            str_contains($e->getMessage(), 'payment required') => trans('bot-owner.safir_payment_required'),
            str_contains($e->getMessage(), 'not valid') => trans('bot-owner.invalid_phone'),
            default => trans('bot-owner.otp_send_failed'),
        };
    }
}
