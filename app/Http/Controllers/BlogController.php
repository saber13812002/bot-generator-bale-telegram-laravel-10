<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\Messenger;
use App\Services\BlogMessengerBroadcastService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Telegram;

class BlogController extends Controller
{
    public function __construct(
        private BlogMessengerBroadcastService $broadcastService
    ) {
    }

    /**
     * Webhook entry: receive message or file from blog bot, broadcast to Telegram, Bale, Eitaa channels.
     */
    public function index(BotRequest $request): JsonResponse
    {
        $type = $request->input('origin');
        $token = $request->has('token') ? $request->input('token') : ($type === 'bale' ? env('BOT_MOTHER_TOKEN_BALE') : env('BOT_MOTHER_TOKEN_TELEGRAM'));
        $bot = $type === 'bale'
            ? new Telegram($token, 'bale')
            : new Telegram($token);

        try {
            LogHelper::log($request, $type, $bot);
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }

        $chatId = $bot->ChatID();
        $messenger = $this->findMessengerByAdminChatId($type, $chatId);

        if (!$messenger) {
            BotHelper::sendMessage($bot, trans('bot.blog_messenger_not_found'));
            return response()->json(['status' => 'messenger_not_found']);
        }

        $text = $bot->Text() ?? '';
        $update = $request->json()->all() ?? $request->all();
        $message = $update['message'] ?? null;

        if ($message && isset($message['text']) && $message['text'] === '/start') {
            BotHelper::sendMessage($bot, trans('bot.blog_start_message'));
            return response()->json(['status' => 'ok']);
        }

        BotHelper::sendMessage($bot, trans('bot.please wait'));

        [$mediaType, $fileId, $caption] = $this->extractMediaFromMessage($message);
        $content = $text ?: $caption;
        if (!$content && !$fileId) {
            BotHelper::sendMessage($bot, trans('bot.blog_no_content'));
            return response()->json(['status' => 'no_content']);
        }

        $results = $this->broadcastService->broadcast(
            $messenger,
            $content,
            $mediaType,
            $fileId,
            $caption,
            $type,
            $token
        );

        $summary = $this->formatBroadcastSummary($results);
        BotHelper::sendMessage($bot, $summary);

        return response()->json(['status' => 'ok', 'results' => $results]);
    }

    private function findMessengerByAdminChatId(string $type, $chatId): ?Messenger
    {
        if ($type === 'telegram') {
            return Messenger::byTelegramAdminChatId($chatId)->first();
        }
        if ($type === 'bale') {
            return Messenger::byBaleAdminChatId($chatId)->first();
        }
        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: string} [mediaType, fileId, caption]
     */
    private function extractMediaFromMessage(?array $message): array
    {
        if (!$message) {
            return [null, null, ''];
        }
        $caption = $message['caption'] ?? '';

        if (isset($message['photo'])) {
            $photos = $message['photo'];
            $photo = end($photos);
            return ['photo', $photo['file_id'] ?? null, $caption];
        }
        if (isset($message['video'])) {
            return ['video', $message['video']['file_id'] ?? null, $caption];
        }
        if (isset($message['voice'])) {
            return ['voice', $message['voice']['file_id'] ?? null, $caption];
        }
        if (isset($message['audio'])) {
            return ['audio', $message['audio']['file_id'] ?? null, $caption];
        }
        if (isset($message['document'])) {
            return ['document', $message['document']['file_id'] ?? null, $caption];
        }

        return [null, null, $caption];
    }

    private function formatBroadcastSummary(array $results): string
    {
        $lines = [];
        foreach ($results as $platform => $result) {
            $label = $platform === 'telegram' ? 'تلگرام' : ($platform === 'bale' ? 'بله' : 'ایتا');
            $lines[] = $result['success'] ? "✅ {$label}: ارسال شد" : "❌ {$label}: " . ($result['error'] ?? 'خطا');
        }
        return empty($lines) ? 'هیچ کانالی پیکربندی نشده است.' : implode("\n", $lines);
    }
}
