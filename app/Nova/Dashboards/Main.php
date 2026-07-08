<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\ActiveBotAccounts;
use App\Nova\Metrics\BotUsersByOrigin;
use App\Nova\Metrics\BotUsersPerBot;
use App\Nova\Metrics\BotUsersPerDay;
use App\Nova\Metrics\NewBotUsers;
use App\Nova\Metrics\NewBotUsersGrowth;
use App\Nova\Metrics\NewUsers;
use App\Nova\Metrics\TotalRegisteredBotUsers;
use App\Nova\Metrics\UniqueBotUsers;
use App\Nova\Metrics\UniqueBotUsersTrend;
use App\Nova\Metrics\UsersPerDay;
use App\Nova\Metrics\VoiceRequestsCount;
use Laravel\Nova\Cards\Help;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return [
            // ===== Platform Overview =====
            (new NewUsers)->width('1/3'),
            (new UsersPerDay)->width('1/3'),
            (new TotalRegisteredBotUsers)->width('1/3'),

            // ===== Bot Users Statistics =====
            (new NewBotUsers)->width('1/3'),
            (new NewBotUsersGrowth)->width('1/3'),
            (new ActiveBotAccounts)->width('1/3'),

            // ===== Unique Users (Active Users) =====
            (new UniqueBotUsers)->width('1/2'),
            (new UniqueBotUsersTrend)->width('1/2'),

            // ===== User Breakdown =====
            (new BotUsersPerBot)->width('1/2'),
            (new BotUsersByOrigin)->width('1/2'),

            // ===== Content & Activity =====
            (new BotUsersPerDay)->width('1/3'),
            (new VoiceRequestsCount)->width('1/3'),
        ];
    }
}
