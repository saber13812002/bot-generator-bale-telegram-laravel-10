<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Interfaces\Repositories\ContentRepository;
use App\Interfaces\Services\ContentService;
use App\Models\Content;
use App\Models\Mission;
use App\Models\MissionContent;
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

            // Handle commands
            if ($text == '/start') {
                $this->handleStart($bot);
            } elseif (str_starts_with($text, '/upload_mission_')) {
                // Format: /upload_mission_123
                $missionId = (int) str_replace('/upload_mission_', '', $text);
                $this->handleUploadMedia($bot, $missionId, $type);
            } elseif (str_starts_with($text, '/get_training_')) {
                // Format: /get_training_123
                $missionId = (int) str_replace('/get_training_', '', $text);
                $this->handleGetTraining($bot, $missionId, $type);
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
        $message .= "دستورات:\n";
        $message .= "/upload_mission_{id} - آپلود مدیا برای ماموریت\n";
        $message .= "/get_training_{id} - دریافت آموزش‌های یک ماموریت\n\n";
        $message .= "مثال: /upload_mission_1";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Handle media upload for a mission.
     */
    private function handleUploadMedia($bot, int $missionId, string $type): void
    {
        Log::info('📤 Mission Media Bot - Upload media request', [
            'mission_id' => $missionId,
            'type' => $type
        ]);

        $mission = Mission::find($missionId);
        if (!$mission) {
            BotHelper::sendMessage($bot, "❌ ماموریت یافت نشد.");
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
        }

        if (!$fileId) {
            BotHelper::sendMessage($bot, "❌ لطفا یک فایل (عکس، ویدیو، صوت یا PDF) ارسال کنید.");
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
        $content = $this->contentRepository->create([
            'tenant_id' => $mission->tenant_id,
            'title' => $mission->title . ' - ' . $fileType,
            'content_type' => $fileType,
            'content_url' => $fileUrl,
            'description' => 'مدیا آموزشی برای ماموریت ' . $mission->title,
            'sort_order' => MissionContent::where('mission_id', $missionId)->max('sort_order') + 1 ?? 1,
        ]);

        // Attach to mission
        MissionContent::create([
            'mission_id' => $missionId,
            'content_id' => $content->id,
            'sort_order' => $content->sort_order,
        ]);

        BotHelper::sendMessage($bot, "✅ مدیا با موفقیت به ماموریت اضافه شد.");
        Log::info('✅ Mission Media Bot - Media uploaded', [
            'mission_id' => $missionId,
            'content_id' => $content->id
        ]);
    }

    /**
     * Handle get training command.
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

        BotHelper::sendMessage($bot, $message);
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
