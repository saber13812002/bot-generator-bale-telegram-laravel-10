<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Models\BotLog;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class BotMessageBroadcastService
{
    /**
     * ارسال پیام به کاربران بر اساس زبان
     * 
     * @param string $language کد زبان
     * @param string $message پیام برای ارسال
     * @param string $botType نوع ربات ('bale' یا 'telegram')
     * @param int $botMotherId شناسه ربات مادر
     * @param string|null $endpointUri فیلتر بر اساس endpoint (اختیاری)
     * @return array نتیجه شامل تعداد ارسال شده و خطاها
     */
    public function sendMessageToUsersByLanguage(
        string $language,
        string $message,
        string $botType,
        int $botMotherId,
        ?string $endpointUri = null
    ): array {
        $result = [
            'success_count' => 0,
            'error_count' => 0,
            'errors' => [],
        ];
        
        try {
            // دریافت لیست کاربران از BotLog
            $query = BotLog::where('bot_mother_id', $botMotherId)
                ->where('language', $language)
                ->where('type', $botType);
            
            if ($endpointUri) {
                $query->where('webhook_endpoint_uri', $endpointUri);
            }
            
            $userChatIds = $query->distinct('chat_id')
                ->pluck('chat_id')
                ->toArray();
            
            if (empty($userChatIds)) {
                Log::info('No users found for broadcast', [
                    'language' => $language,
                    'bot_type' => $botType,
                    'bot_mother_id' => $botMotherId,
                    'endpoint_uri' => $endpointUri,
                ]);
                
                return $result;
            }
            
            // دریافت توکن ربات مادر
            $token = $botType == 'bale' 
                ? env('BOT_MOTHER_TOKEN_BALE') 
                : env('BOT_MOTHER_TOKEN_TELEGRAM');
            
            if (!$token) {
                throw new Exception("Token not found for bot type: {$botType}");
            }
            
            $bot = new Telegram($token, $botType);
            
            // ارسال پیام به هر کاربر
            foreach ($userChatIds as $chatId) {
                try {
                    BotHelper::sendMessageByChatId($bot, $chatId, $message);
                    $result['success_count']++;
                    
                    // Rate limiting: تاخیر 100ms بین هر پیام
                    usleep(100000);
                    
                } catch (Exception $e) {
                    $result['error_count']++;
                    $result['errors'][] = [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage(),
                    ];
                    
                    Log::error('Error sending broadcast message to user', [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            Log::info('Broadcast completed', [
                'language' => $language,
                'bot_type' => $botType,
                'bot_mother_id' => $botMotherId,
                'endpoint_uri' => $endpointUri,
                'total_users' => count($userChatIds),
                'success_count' => $result['success_count'],
                'error_count' => $result['error_count'],
            ]);
            
            // ارسال گزارش به ادمین‌ها
            $this->sendBroadcastReportToAdmins($result, $language, $botType, $botMotherId, count($userChatIds));
            
        } catch (Exception $e) {
            Log::error('Error in broadcast service', [
                'error' => $e->getMessage(),
                'language' => $language,
                'bot_type' => $botType,
                'bot_mother_id' => $botMotherId,
            ]);
            
            throw $e;
        }
        
        return $result;
    }
    
    /**
     * ارسال پیام به کاربران یک ربات خاص
     * 
     * @param int $botId شناسه ربات
     * @param string $message پیام برای ارسال
     * @param string $botType نوع ربات
     * @return array نتیجه شامل تعداد ارسال شده و خطاها
     */
    public function sendMessageToBotUsers(
        int $botId,
        string $message,
        string $botType
    ): array {
        $result = [
            'success_count' => 0,
            'error_count' => 0,
            'errors' => [],
        ];
        
        try {
            // دریافت لیست کاربران از BotLog
            $userChatIds = BotLog::where('bot_id', $botId)
                ->where('type', $botType)
                ->distinct('chat_id')
                ->pluck('chat_id')
                ->toArray();
            
            if (empty($userChatIds)) {
                return $result;
            }
            
            // دریافت توکن ربات
            $token = $botType == 'bale' 
                ? env('BOT_MOTHER_TOKEN_BALE') 
                : env('BOT_MOTHER_TOKEN_TELEGRAM');
            
            if (!$token) {
                throw new Exception("Token not found for bot type: {$botType}");
            }
            
            $bot = new Telegram($token, $botType);
            
            // ارسال پیام به هر کاربر
            foreach ($userChatIds as $chatId) {
                try {
                    BotHelper::sendMessageByChatId($bot, $chatId, $message);
                    $result['success_count']++;
                    
                    // Rate limiting
                    usleep(100000);
                    
                } catch (Exception $e) {
                    $result['error_count']++;
                    $result['errors'][] = [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
        } catch (Exception $e) {
            Log::error('Error sending message to bot users', [
                'error' => $e->getMessage(),
                'bot_id' => $botId,
                'bot_type' => $botType,
            ]);
            
            throw $e;
        }
        
        return $result;
    }
    
    /**
     * ارسال گزارش broadcast به ادمین‌ها
     * 
     * @param array $result نتیجه broadcast
     * @param string $language زبان
     * @param string $botType نوع ربات
     * @param int $botMotherId شناسه ربات مادر
     * @param int $totalUsers تعداد کل کاربران
     * @return void
     */
    private function sendBroadcastReportToAdmins(array $result, string $language, string $botType, int $botMotherId, int $totalUsers): void
    {
        try {
            $reportMessage = "📢 گزارش ارسال پیام همگانی\n\n";
            $reportMessage .= "🌍 زبان: {$language}\n";
            $reportMessage .= "🔧 نوع ربات: {$botType}\n";
            $reportMessage .= "🆔 Bot Mother ID: {$botMotherId}\n";
            $reportMessage .= "👥 تعداد کل کاربران: {$totalUsers}\n";
            $reportMessage .= "✅ ارسال موفق: {$result['success_count']}\n";
            $reportMessage .= "❌ خطا: {$result['error_count']}\n";
            
            if ($result['error_count'] > 0 && count($result['errors']) > 0) {
                $reportMessage .= "\n⚠️ خطاها:\n";
                foreach (array_slice($result['errors'], 0, 10) as $error) {
                    $reportMessage .= "• Chat ID {$error['chat_id']}: {$error['error']}\n";
                }
                if (count($result['errors']) > 10) {
                    $reportMessage .= "... و " . (count($result['errors']) - 10) . " خطای دیگر\n";
                }
            }
            
            // ارسال به همه ادمین‌ها
            BotHelper::sendMessageToSuperAdmin($reportMessage, $botType);
            
            Log::info('Broadcast report sent to admins', [
                'bot_type' => $botType,
                'bot_mother_id' => $botMotherId,
                'language' => $language,
            ]);
            
        } catch (Exception $e) {
            Log::error('Error sending broadcast report to admins', [
                'error' => $e->getMessage(),
                'bot_type' => $botType,
            ]);
        }
    }
}
