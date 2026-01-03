<?php

namespace App\Helpers;

use App\Http\Requests\BotRequest;
use App\Models\Bot;
use App\Models\BotLog;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram;

class LogHelper
{

    /**
     * @param BotRequest $request
     * @param mixed $type
     * @param Telegram $bot
     * @return void
     */
    public static function log(BotRequest $request, mixed $type, $bot): void
    {
        $log = new BotLog();
        $log->webhook_endpoint_uri = request()->segment(2);
        $log->bot_mother_id = $request->input('bot_mother_id') ?? 0;
        $log->language = $request->input('language');
        $log->command_type = $request->request->get('command_type');
        $log->locale = App::getLocale();
        $log->type = $type;
        $log->text = mb_convert_encoding(substr($bot->Text(), 0, 199), 'UTF-8', 'UTF-8');
        $log->is_command = str_starts_with($bot->Text(), "/");
        $log->channel_group_type = $bot->ChatID() < 0 ? $bot->ChatID() : 0;
        
        // پیدا کردن bot_id از token و type
        $botId = self::findBotIdFromToken($request, $type);
        $log->bot_id = $botId;
        
        $log->chat_id = $bot->ChatID();
        $log->save();
    }
    
    /**
     * پیدا کردن bot_id از token و type
     * 
     * @param BotRequest $request
     * @param string $type
     * @return int
     */
    private static function findBotIdFromToken(BotRequest $request, string $type): int
    {
        try {
            // دریافت token از request
            $token = null;
            if ($request->has('token')) {
                $token = $request->input('token');
            }
            
            // اگر token وجود داشت، جستجو در جدول bots
            if ($token) {
                $bot = null;
                if ($type == 'telegram') {
                    $bot = Bot::where('telegram_bot_token', $token)->first();
                } elseif ($type == 'bale') {
                    $bot = Bot::where('bale_bot_token', $token)->first();
                }
                
                if ($bot) {
                    return $bot->id;
                }
            }
            
            // اگر token پیدا نشد یا bot پیدا نشد، از webhook_endpoint_uri و language استفاده می‌کنیم
            $webhookEndpointUri = request()->segment(2);
            $botMotherId = $request->input('bot_mother_id') ?? 0;
            $language = $request->input('language');
            
            // اگر webhook_endpoint_uri و language و bot_mother_id وجود داشتند، سعی می‌کنیم bot_id را پیدا کنیم
            if ($webhookEndpointUri && $botMotherId > 0 && $language) {
                // پیدا کردن ربات از جدول bots بر اساس bot_mother_id و type و language
                // اما language در bots نیست، پس باید از BotLog استفاده کنیم
                // راه حل: پیدا کردن bot_id از BotLog بر اساس webhook_endpoint_uri و language و type
                // اما فقط اگر bot_id درست باشد (نه 1)
                $botLog = BotLog::where('bot_mother_id', $botMotherId)
                    ->where('type', $type)
                    ->where('webhook_endpoint_uri', $webhookEndpointUri)
                    ->where('language', $language)
                    ->whereNotNull('bot_id')
                    ->where('bot_id', '!=', 1) // bot_id = 1 مربوط به Bot Mother است
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($botLog && $botLog->bot_id) {
                    // بررسی اینکه آیا این bot_id در جدول bots وجود دارد
                    $bot = Bot::find($botLog->bot_id);
                    if ($bot) {
                        return $bot->id;
                    }
                }
                
                // اگر bot_id پیدا نشد، از ربات‌های موجود در bots استفاده می‌کنیم
                // پیدا کردن ربات بر اساس bot_mother_id و type
                $query = Bot::where('bot_mother_id', $botMotherId);
                if ($type == 'telegram') {
                    $query->whereNotNull('telegram_bot_token')
                        ->where('telegram_bot_token', '!=', '');
                } elseif ($type == 'bale') {
                    $query->whereNotNull('bale_bot_token')
                        ->where('bale_bot_token', '!=', '');
                }
                
                // پیدا کردن رباتی که برای این language لاگ دارد
                $bots = $query->get();
                foreach ($bots as $bot) {
                    // بررسی اینکه آیا این ربات برای این language لاگ دارد
                    $hasLog = BotLog::where('bot_mother_id', $botMotherId)
                        ->where('type', $type)
                        ->where('webhook_endpoint_uri', $webhookEndpointUri)
                        ->where('language', $language)
                        ->where('bot_id', $bot->id)
                        ->exists();
                    
                    if ($hasLog) {
                        return $bot->id;
                    }
                }
            }
            
            // اگر هیچکدام کار نکرد، از env استفاده می‌کنیم (برای Bot Mother)
            if (!$token) {
                if ($type == 'bale') {
                    $token = env("BOT_MOTHER_TOKEN_BALE");
                } else {
                    $token = env("BOT_MOTHER_TOKEN_TELEGRAM");
                }
            }
            
            // آخرین تلاش: جستجو در bots بر اساس token از env
            if ($token) {
                $bot = null;
                if ($type == 'telegram') {
                    $bot = Bot::where('telegram_bot_token', $token)->first();
                } elseif ($type == 'bale') {
                    $bot = Bot::where('bale_bot_token', $token)->first();
                }
                
                if ($bot) {
                    return $bot->id;
                }
            }
            
            // اگر هیچکدام کار نکرد، از 1 استفاده می‌کنیم (Bot Mother)
            if ($token) {
                Log::warning('Bot not found for token', [
                    'type' => $type,
                    'token_prefix' => substr($token, 0, 10) . '...',
                    'webhook_endpoint_uri' => $webhookEndpointUri,
                    'bot_mother_id' => $botMotherId,
                    'language' => $language,
                ]);
            }
            return 1;
            
        } catch (\Exception $e) {
            Log::error('Error finding bot_id from token', [
                'error' => $e->getMessage(),
                'type' => $type,
            ]);
            // در صورت خطا، از 1 استفاده می‌کنیم
            return 1;
        }
    }

    public static function isLastLogAvailable(BotRequest $request, Telegram $bot): array
    {
        $log = BotLog::query()
            ->whereWebhookEndpointUri(request()->segment(2))
            ->whereBotMotherId($request->input('bot_mother_id'))
            ->whereLanguage($request->input('language'))
            ->whereCommandType('search')
            ->whereLocale(App::getLocale())
            ->whereType($bot->BotType())
            ->whereIsCommand(1)
            ->whereChannelGroupType($bot->ChatID() < 0 ? $bot->ChatID() : 0)
            ->whereChatId($bot->ChatID())
            ->orderby('created_at', 'desc')
            ->first();

        $phrase = $bot->Text();
        $lastStatus = '';
        if ($log) {
            $lastStatus = 'search';
        }
        return [$lastStatus, $phrase];
    }
}
