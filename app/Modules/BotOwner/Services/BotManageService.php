<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\LibraryPlanRequest;
use App\Models\BotUsers;
use App\Models\ContentPendingUpload;
use App\Modules\BotOwner\Contracts\BotManageServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;

class BotManageService implements BotManageServiceInterface
{
    public function getManageData(Bot $bot, BotOwner $owner): array
    {
        $stats = $this->getBotStats($bot);

        $bot->load('webhookEndpoint');

        return [
            'bot' => $bot,
            'stats' => $stats,
            'sections' => $this->getAvailableSections($bot),
        ];
    }

    public function getBotStats(Bot $bot): array
    {
        $totalUsers = BotUsers::where('bot_id', $bot->id)->count();
        $pendingAdminKie = BotAdminKieRequest::where('bot_id', $bot->id)->pending()->count();
        $pendingPlanRequests = LibraryPlanRequest::where('bot_id', $bot->id)->pending()->count();
        $pendingUploads = ContentPendingUpload::where('bot_id', $bot->id)->count();
        $categoriesCount = ContentCategory::where('bot_id', $bot->id)->count();
        $itemsCount = ContentItem::where('bot_id', $bot->id)->count();

        return [
            'total_users' => $totalUsers,
            'pending_admin_kie' => $pendingAdminKie,
            'pending_plan_requests' => $pendingPlanRequests,
            'pending_uploads' => $pendingUploads,
            'categories_count' => $categoriesCount,
            'items_count' => $itemsCount,
        ];
    }

    private function getAvailableSections(Bot $bot): array
    {
        $sections = [];

        // Admin Kie is available for all bots
        $sections[] = [
            'id' => 'admin-kie',
            'route' => route('bot-owner.manage.admin-kie', $bot->id),
            'title_key' => 'section_admin_kie',
            'description_key' => 'section_admin_kie_desc',
        ];

        // Plans - available for book-library type bots
        if (in_array($bot->endpoint_id, ['book-library', 'book-library-reader'])) {
            $sections[] = [
                'id' => 'plans',
                'route' => route('bot-owner.manage.plans', $bot->id),
                'title_key' => 'section_plans',
                'description_key' => 'section_plans_desc',
            ];
        }

        // Categories & Items - for content-based bots
        if (in_array($bot->endpoint_id, [
            'book-library', 'book-library-reader', 'content-submission',
            'presenter-bot', 'audio-book', 'book-pixel',
        ])) {
            $sections[] = [
                'id' => 'categories',
                'route' => route('bot-owner.manage.categories', $bot->id),
                'title_key' => 'section_categories',
                'description_key' => 'section_categories_desc',
            ];
            $sections[] = [
                'id' => 'items',
                'route' => route('bot-owner.manage.items', $bot->id),
                'title_key' => 'section_items',
                'description_key' => 'section_items_desc',
            ];
            $sections[] = [
                'id' => 'uploads',
                'route' => route('bot-owner.manage.uploads', $bot->id),
                'title_key' => 'section_uploads',
                'description_key' => 'section_uploads_desc',
            ];
        }

        // Bot-specific settings
        $sections[] = [
            'id' => 'settings',
            'route' => route('bot-owner.manage.settings', $bot->id),
            'title_key' => 'section_settings',
            'description_key' => 'section_settings_desc',
        ];

        return $sections;
    }
}
