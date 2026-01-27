<?php

namespace App\Services;

use App\Interfaces\Services\BookStatisticsService;
use App\Models\Book;
use App\Models\BookDraft;
use App\Models\BookModerationGroup;
use App\Models\BookPageScan;
use App\Models\BookPublishingChannel;
use App\Models\BookPublishingQueue;
use App\Models\BookScanMission;
use App\Models\BotUsers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookStatisticsServiceImpl implements BookStatisticsService
{
    public function getFullStats(int $botId): array
    {
        Log::info('BookStatisticsService - Getting full stats', ['bot_id' => $botId]);

        $now = now();
        $today = $now->copy()->startOfDay();
        $lastWeek = $now->copy()->subWeek()->startOfDay();
        $lastMonth = $now->copy()->subMonth()->startOfDay();
        $lastYear = $now->copy()->subYear()->startOfDay();

        // Queue stats
        $pendingApproval = BookPageScan::where('bot_id', $botId)
            ->where('status', 'pending_approval')
            ->count();

        $pendingPublishing = BookPublishingQueue::where('bot_id', $botId)
            ->where('status', 'pending')
            ->count();

        // Completed stats
        $completedToday = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->whereDate('approved_at', '>=', $today)
            ->count();

        $completedLastWeek = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->where('approved_at', '>=', $lastWeek)
            ->count();

        $completedLastMonth = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->where('approved_at', '>=', $lastMonth)
            ->count();

        $completedLastYear = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->where('approved_at', '>=', $lastYear)
            ->count();

        // Publishing queue
        $inPublishingQueue = BookPublishingQueue::where('bot_id', $botId)
            ->where('status', 'pending')
            ->count();

        // Channels
        $publishingChannels = BookPublishingChannel::where('bot_id', $botId)
            ->where('is_active', true)
            ->count();

        // New users (today)
        $newUsersToday = BotUsers::where('bot_id', $botId)
            ->whereDate('created_at', '>=', $today)
            ->count();

        // New books (today)
        $newBooksToday = Book::where('bot_id', $botId)
            ->whereDate('created_at', '>=', $today)
            ->count();

        // Incomplete drafts
        $incompleteDrafts = BookDraft::where('bot_id', $botId)
            ->where('status', 'draft')
            ->count();

        // Missions
        $pendingMissions = BookScanMission::where('bot_id', $botId)
            ->where('status', 'pending')
            ->count();

        $assignedMissions = BookScanMission::where('bot_id', $botId)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->count();

        // Active users (users who completed scans)
        $activeUsersToday = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->whereDate('approved_at', '>=', $today)
            ->distinct('user_id')
            ->count('user_id');

        $activeUsersLastWeek = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->where('approved_at', '>=', $lastWeek)
            ->distinct('user_id')
            ->count('user_id');

        $activeUsersLastMonth = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->where('approved_at', '>=', $lastMonth)
            ->distinct('user_id')
            ->count('user_id');

        $activeUsersLastYear = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->where('approved_at', '>=', $lastYear)
            ->distinct('user_id')
            ->count('user_id');

        // Mission completers
        $missionCompletersToday = BookScanMission::where('bot_id', $botId)
            ->where('status', 'completed')
            ->whereDate('completed_at', '>=', $today)
            ->distinct('assigned_to_chat_id')
            ->count('assigned_to_chat_id');

        $missionCompletersLastWeek = BookScanMission::where('bot_id', $botId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $lastWeek)
            ->distinct('assigned_to_chat_id')
            ->count('assigned_to_chat_id');

        $missionCompletersLastMonth = BookScanMission::where('bot_id', $botId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $lastMonth)
            ->distinct('assigned_to_chat_id')
            ->count('assigned_to_chat_id');

        $missionCompletersLastYear = BookScanMission::where('bot_id', $botId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $lastYear)
            ->distinct('assigned_to_chat_id')
            ->count('assigned_to_chat_id');

        // Previous period comparisons
        $previousWeekStart = $lastWeek->copy()->subWeek();
        $previousMonthStart = $lastMonth->copy()->subMonth();
        $previousYearStart = $lastYear->copy()->subYear();

        $completedPreviousWeek = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->whereBetween('approved_at', [$previousWeekStart, $lastWeek])
            ->count();

        $completedPreviousMonth = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->whereBetween('approved_at', [$previousMonthStart, $lastMonth])
            ->count();

        $completedPreviousYear = BookPageScan::where('bot_id', $botId)
            ->where('status', 'approved')
            ->whereBetween('approved_at', [$previousYearStart, $lastYear])
            ->count();

        return [
            'queue' => [
                'pending_approval' => $pendingApproval,
                'pending_publishing' => $pendingPublishing,
                'in_publishing_queue' => $inPublishingQueue,
            ],
            'completed' => [
                'today' => $completedToday,
                'last_week' => $completedLastWeek,
                'last_month' => $completedLastMonth,
                'last_year' => $completedLastYear,
            ],
            'comparison' => [
                'week' => $completedLastWeek - $completedPreviousWeek,
                'month' => $completedLastMonth - $completedPreviousMonth,
                'year' => $completedLastYear - $completedPreviousYear,
            ],
            'channels' => $publishingChannels,
            'new' => [
                'users_today' => $newUsersToday,
                'books_today' => $newBooksToday,
            ],
            'drafts' => [
                'incomplete' => $incompleteDrafts,
            ],
            'missions' => [
                'pending' => $pendingMissions,
                'assigned' => $assignedMissions,
            ],
            'active_users' => [
                'today' => $activeUsersToday,
                'last_week' => $activeUsersLastWeek,
                'last_month' => $activeUsersLastMonth,
                'last_year' => $activeUsersLastYear,
            ],
            'mission_completers' => [
                'today' => $missionCompletersToday,
                'last_week' => $missionCompletersLastWeek,
                'last_month' => $missionCompletersLastMonth,
                'last_year' => $missionCompletersLastYear,
            ],
        ];
    }
}
