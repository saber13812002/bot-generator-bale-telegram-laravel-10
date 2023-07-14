<?php

namespace App\Interfaces\Services;

interface QuranBotUserRankingService
{
    public function sendToAllUsers();

    public function specificUserReport($chatId, \Telegram $bot = null);

}
