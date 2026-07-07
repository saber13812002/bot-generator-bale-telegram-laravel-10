<?php

namespace App\Services;

use App\Helpers\EmailAdminHelper;
use App\Models\LibraryPlanRequest;
use Illuminate\Support\Facades\Log;

class LibraryPlanNotificationService
{
    public function notifyAdmins(LibraryPlanRequest $request): void
    {
        $botUser = $request->botUser;
        $bot = $request->bot;
        $planLabel = trans(config('book_library.plans.' . $request->plan . '.label_key', 'book_library.plan.free'));

        $message = "💎 درخواست ارتقای پلن کتابخانه\n\n";
        $message .= "👤 User: {$request->user_identifier}\n";
        $message .= "💬 Chat ID: {$botUser->chat_id}\n";
        $message .= "🤖 Bot ID: {$request->bot_id}\n";
        $message .= "📦 Plan: {$planLabel} ({$request->plan})\n";
        $message .= "🆔 Request ID: {$request->id}\n";
        $message .= "📅 Created: " . $request->created_at->format('Y-m-d H:i') . "\n\n";
        $message .= "✅ تایید در ربات:\n";
        $message .= "/library_plan_confirm {$request->id}\n\n";
        $message .= "✅ تایید در Nova:\n";
        $message .= "🔗 http://bots.pardisania.ir/nova/resources/library-plan-requests/{$request->id}\n\n";
        $message .= "❌ رد:\n";
        $message .= "/library_plan_reject {$request->id}";

        try {
            EmailAdminHelper::sendToAllAdmins($message, 'bale');
            EmailAdminHelper::sendToAllAdmins($message, 'telegram');
        } catch (\Exception $e) {
            Log::error('❌ [LibraryPlanNotification] Error', ['error' => $e->getMessage()]);
        }
    }
}
