<?php

namespace App\Modules\BotOwner\Repositories;

use App\Modules\BotOwner\Contracts\BotOwnerOtpSessionRepositoryInterface;
use App\Modules\BotOwner\Models\BotOwnerOtpSession;
use Carbon\Carbon;

class BotOwnerOtpSessionRepository implements BotOwnerOtpSessionRepositoryInterface
{
    public function createSession(string $phone, string $otpHash, \DateTimeInterface $expiresAt, ?string $ip = null): BotOwnerOtpSession
    {
        $this->deleteForPhone($phone);

        return BotOwnerOtpSession::create([
            'phone' => $phone,
            'otp_hash' => $otpHash,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'ip' => $ip,
        ]);
    }

    public function findLatestForPhone(string $phone): ?BotOwnerOtpSession
    {
        return BotOwnerOtpSession::where('phone', $phone)
            ->orderByDesc('id')
            ->first();
    }

    public function deleteForPhone(string $phone): void
    {
        BotOwnerOtpSession::where('phone', $phone)->delete();
    }

    public function countRecentForPhone(string $phone, int $hours = 1): int
    {
        return BotOwnerOtpSession::where('phone', $phone)
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->count();
    }
}
