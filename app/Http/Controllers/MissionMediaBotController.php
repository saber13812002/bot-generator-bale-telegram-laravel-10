<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\BotMotherStateHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Repositories\ContentRepository;
use App\Interfaces\Services\ContentService;
use App\Models\Content;
use App\Models\Mission;
use App\Models\MissionContent;
use App\Models\Personnel;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class MissionMediaBotController extends Controller
{
    private ContentService $contentService;
    private ContentRepository $contentRepository;

    public function __construct(
        ContentService $contentService,
        ContentRepository $contentRepository
    ) {
        $this->contentService = $contentService;
        $this->contentRepository = $contentRepository;
    }

    /**
     * Handle mission media bot webhook
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        Log::info('🔔 Mission Media Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
            'timestamp' => now()->toDateTimeString()
        ]);

        try {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');

            if ($type == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env('MISSION_MEDIA_BOT_TOKEN_BALE');
                $bot = new Telegram($token, 'bale');
            } else {
                $token = $request->has('token') ? $request->input('token') : env('MISSION_MEDIA_BOT_TOKEN_TELEGRAM');
                $bot = new Telegram($token);
            }

            // Verify webhook is set correctly
            $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
            Log::info('📡 Mission Media Bot - Webhook status check', [
                'type' => $type,
                'webhook_ok' => $webhookInfo['ok'] ?? false,
                'webhook_url' => $webhookInfo['result']['url'] ?? null,
                'pending_updates' => $webhookInfo['result']['pending_update_count'] ?? 0
            ]);

            // Log the request
            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            $text = $bot->Text();
            $chatId = $bot->ChatID();

            // Check if user is admin
            if (!AdminHelper::isAdmin($chatId)) {
                $message = "❌ شما دسترسی به این ربات ندارید.\nاین ربات فقط برای ادمین‌ها قابل استفاده است.";
                BotHelper::sendMessage($bot, $message);
                Log::warning('⚠️ Mission Media Bot - Unauthorized access attempt', ['chat_id' => $chatId]);
                return;
            }

            // Check current state for file upload
            $currentState = BotMotherStateHelper::getCurrentState($chatId);
            $stateData = BotMotherStateHelper::getData($chatId);

            // Handle commands
            if ($text == '/start') {
                $this->handleStart($bot);
                BotMotherStateHelper::setState($chatId, null, []); // Clear state
            } elseif (str_starts_with($text, '/upload_mission_')) {
                // Format: /upload_mission_123
                $missionId = (int) str_replace('/upload_mission_', '', $text);
                $this->handleUploadMediaStart($bot, $missionId, $chatId);
            } elseif (str_starts_with($text, '/get_training_')) {
                // Format: /get_training_123
                $missionId = (int) str_replace('/get_training_', '', $text);
                $this->handleGetTraining($bot, $missionId, $type);
            } elseif (str_starts_with($text, '/send_training_')) {
                // Format: /send_training_123_to_456 (mission_id_to_personnel_id)
                $parts = explode('_to_', str_replace('/send_training_', '', $text));
                if (count($parts) == 2) {
                    $missionId = (int) $parts[0];
                    $personnelId = (int) $parts[1];
                    $this->handleSendTrainingToPersonnel($bot, $missionId, $personnelId, $type);
                } else {
                    BotHelper::sendMessage($bot, "❌ فرمت دستور اشتباه است.\nفرمت صحیح: /send_training_{mission_id}_to_{personnel_id}");
                }
            } elseif ($currentState == 'uploading_media' && isset($stateData['mission_id'])) {
                // User is in upload mode, handle file upload
                $this->handleUploadMediaFile($bot, $stateData['mission_id'], $type, $chatId);
            } else {
                BotHelper::sendMessage($bot, "دستور نامعتبر است. از /start برای مشاهده دستورات استفاده کنید.");
            }

        } catch (Exception $e) {
            Log::error('❌ Mission Media Bot - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chatId ?? null,
                'text' => $text ?? null,
                'origin' => $request->input('origin') ?? null
            ]);
            if (isset($bot)) {
                BotHelper::sendMessage($bot, 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
            }
        }
    }

    /**
     * Handle start command.
     */
    private function handleStart($bot): void
    {
        $message = "👋 سلام! ربات مدیریت مدیا ماموریت‌ها\n\n";
        $message .= "📋 دستورات:\n\n";
        $message .= "1️⃣ آپلود مدیا:\n";
        $message .= "   /upload_mission_{id}\n";
        $message .= "   مثال: /upload_mission_1\n";
        $message .= "   سپس فایل‌ها را ارسال کنید و /done برای پایان\n\n";
        $message .= "2️⃣ مشاهده لیست آموزش‌ها:\n";
        $message .= "   /get_training_{id}\n";
        $message .= "   مثال: /get_training_1\n\n";
        $message .= "3️⃣ ارسال آموزش به پرسنل:\n";
        $message .= "   /send_training_{mission_id}_to_{personnel_id}\n";
        $message .= "   مثال: /send_training_1_to_5\n\n";
        $message .= "💡 نکته: بعد از /upload_mission_{id} فایل‌ها را ارسال کنید و /done برای پایان.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Start media upload process for a mission.
     */
    private function handleUploadMediaStart($bot, int $missionId, $chatId): void
    {
        Log::info('📤 Mission Media Bot - Upload media start', [
            'mission_id' => $missionId,
            'chat_id' => $chatId
        ]);

        $mission = Mission::find($missionId);
        if (!$mission) {
            BotHelper::sendMessage($bot, "❌ ماموریت یافت نشد.");
            return;
        }

        // Set state for file upload
        BotMotherStateHelper::setState($chatId, 'uploading_media', [
            'mission_id' => $missionId
        ]);

        $message = "📤 آماده دریافت فایل برای ماموریت: " . $mission->title . "\n\n";
        $message .= "لطفا فایل‌های آموزشی را ارسال کنید:\n";
        $message .= "• عکس\n";
        $message .= "• ویدیو\n";
        $message .= "• صوت\n";
        $message .= "• PDF\n\n";
        $message .= "برای پایان آپلود، دستور /done را ارسال کنید.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Handle file upload when in upload mode.
     */
    private function handleUploadMediaFile($bot, int $missionId, string $type, $chatId): void
    {
        Log::info('📤 Mission Media Bot - Upload media file', [
            'mission_id' => $missionId,
            'type' => $type
        ]);

        $mission = Mission::find($missionId);
        if (!$mission) {
            BotHelper::sendMessage($bot, "❌ ماموریت یافت نشد.");
            BotMotherStateHelper::setState($chatId, null, []);
            return;
        }

        // Get file from message
        $update = request()->json()->all() ?? request()->all();
        $fileId = null;
        $fileType = null;

        if (isset($update['message']['photo'])) {
            $photos = $update['message']['photo'];
            $fileId = end($photos)['file_id'];
            $fileType = 'image';
        } elseif (isset($update['message']['video'])) {
            $fileId = $update['message']['video']['file_id'];
            $fileType = 'video';
        } elseif (isset($update['message']['audio'])) {
            $fileId = $update['message']['audio']['file_id'];
            $fileType = 'audio';
        } elseif (isset($update['message']['document'])) {
            $fileId = $update['message']['document']['file_id'];
            $fileType = 'pdf';
        } elseif (isset($update['message']['text']) && strtolower(trim($update['message']['text'])) == '/done') {
            // End upload mode
            BotMotherStateHelper::setState($chatId, null, []);
            BotHelper::sendMessage($bot, "✅ آپلود فایل‌ها به پایان رسید.");
            return;
        }

        if (!$fileId) {
            BotHelper::sendMessage($bot, "❌ لطفا یک فایل (عکس، ویدیو، صوت یا PDF) ارسال کنید یا /done برای پایان.");
            return;
        }

        // Get file URL
        $token = $type == 'bale' ? env('MISSION_MEDIA_BOT_TOKEN_BALE') : env('MISSION_MEDIA_BOT_TOKEN_TELEGRAM');
        $fileUrl = $this->getFileUrl($token, $fileId, $type);

        if (!$fileUrl) {
            BotHelper::sendMessage($bot, "❌ خطا در دریافت فایل.");
            return;
        }

        // Create content
        $maxSortOrder = MissionContent::where('mission_id', $missionId)->max('sort_order') ?? 0;
        $content = $this->contentRepository->create([
            'tenant_id' => $mission->tenant_id,
            'title' => $mission->title . ' - ' . $fileType . ' ' . ($maxSortOrder + 1),
            'content_type' => $fileType,
            'content_url' => $fileUrl,
            'description' => 'مدیا آموزشی برای ماموریت ' . $mission->title,
            'sort_order' => $maxSortOrder + 1,
        ]);

        // Attach to mission
        MissionContent::create([
            'mission_id' => $missionId,
            'content_id' => $content->id,
            'sort_order' => $content->sort_order,
        ]);

        BotHelper::sendMessage($bot, "✅ مدیا با موفقیت به ماموریت اضافه شد.\nبرای پایان آپلود، /done را ارسال کنید.");
        Log::info('✅ Mission Media Bot - Media uploaded', [
            'mission_id' => $missionId,
            'content_id' => $content->id
        ]);
    }

    /**
     * Handle get training command - shows list of training content.
     */
    private function handleGetTraining($bot, int $missionId, string $type): void
    {
        Log::info('📥 Mission Media Bot - Get training request', [
            'mission_id' => $missionId,
            'type' => $type
        ]);

        $mission = Mission::find($missionId);
        if (!$mission) {
            BotHelper::sendMessage($bot, "❌ ماموریت یافت نشد.");
            return;
        }

        $contents = $this->contentRepository->getByMission($missionId);

        if ($contents->isEmpty()) {
            BotHelper::sendMessage($bot, "❌ محتوای آموزشی برای این ماموریت یافت نشد.");
            return;
        }

        $message = "📚 آموزش‌های ماموریت: " . $mission->title . "\n\n";
        $message .= "تعداد محتوا: " . $contents->count() . "\n\n";

        foreach ($contents as $index => $content) {
            $message .= ($index + 1) . ". " . $content->title . " (" . $content->content_type . ")\n";
        }

        $message .= "\n💡 برای ارسال این آموزش‌ها به یک پرسنل:\n";
        $message .= "/send_training_{$missionId}_to_{personnel_id}";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Send training content to a specific personnel.
     */
    private function handleSendTrainingToPersonnel($bot, int $missionId, int $personnelId, string $type): void
    {
        Log::info('📤 Mission Media Bot - Send training to personnel', [
            'mission_id' => $missionId,
            'personnel_id' => $personnelId,
            'type' => $type
        ]);

        $mission = Mission::find($missionId);
        if (!$mission) {
            BotHelper::sendMessage($bot, "❌ ماموریت یافت نشد.");
            return;
        }

        $personnel = Personnel::find($personnelId);
        if (!$personnel) {
            BotHelper::sendMessage($bot, "❌ پرسنل یافت نشد.");
            return;
        }

        try {
            // Use ContentService to send training media
            $this->contentService->sendTrainingMedia($missionId, $personnelId, $type);
            
            BotHelper::sendMessage($bot, "✅ آموزش‌های ماموریت برای پرسنل ارسال شد.\n\n");
            BotHelper::sendMessage($bot, "پرسنل: {$personnel->first_name} {$personnel->last_name}\n");
            BotHelper::sendMessage($bot, "ماموریت: {$mission->title}");
            
            Log::info('✅ Mission Media Bot - Training sent to personnel', [
                'mission_id' => $missionId,
                'personnel_id' => $personnelId
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Mission Media Bot - Error sending training', [
                'error' => $e->getMessage(),
                'mission_id' => $missionId,
                'personnel_id' => $personnelId
            ]);
            BotHelper::sendMessage($bot, "❌ خطا در ارسال آموزش‌ها: " . $e->getMessage());
        }
    }

    /**
     * Get file URL from file_id.
     */
    private function getFileUrl(string $token, string $fileId, string $type): ?string
    {
        try {
            if ($type == 'bale') {
                $url = "https://tapi.bale.ai/bot{$token}/getFile?file_id={$fileId}";
            } else {
                $url = "https://api.telegram.org/bot{$token}/getFile?file_id={$fileId}";
            }

            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (isset($data['result']['file_path'])) {
                $filePath = $data['result']['file_path'];
                if ($type == 'bale') {
                    return "https://tapi.bale.ai/file/bot{$token}/{$filePath}";
                } else {
                    return "https://api.telegram.org/file/bot{$token}/{$filePath}";
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting file URL', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
