<?php

namespace App\Modules\BotOwner\Contracts;

interface BotOwnerAuthServiceInterface
{
    /**
     * @return array{success: bool, message: string}
     */
    public function sendOtp(string $phone, ?string $ip = null): array;

    /**
     * @return array{success: bool, message: string, bot_owner?: \App\Modules\BotOwner\Models\BotOwner}
     */
    public function verifyOtp(string $phone, string $otp): array;

    public function loginSession(\App\Modules\BotOwner\Models\BotOwner $owner): void;

    public function logoutSession(): void;

    public function currentOwner(): ?\App\Modules\BotOwner\Models\BotOwner;
}
