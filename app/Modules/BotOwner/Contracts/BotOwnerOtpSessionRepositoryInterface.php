<?php

namespace App\Modules\BotOwner\Contracts;

use App\Modules\BotOwner\Models\BotOwnerOtpSession;

interface BotOwnerOtpSessionRepositoryInterface
{
    public function createSession(string $phone, string $otpHash, \DateTimeInterface $expiresAt, ?string $ip = null): BotOwnerOtpSession;

    public function findLatestForPhone(string $phone): ?BotOwnerOtpSession;

    public function deleteForPhone(string $phone): void;

    public function countRecentForPhone(string $phone, int $hours = 1): int;
}
