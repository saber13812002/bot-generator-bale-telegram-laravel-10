<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class QuranBotsIntroductionService
{
    /**
     * دریافت ربات‌ها از فایل کانفیگ
     * 
     * @return array
     */
    public function getBotsFromConfig(): array
    {
        $config = config('quran_bots.bots', []);
        return $config;
    }

    /**
     * دریافت ربات‌های قرآن از دیتابیس
     * 
     * @param int $botMotherId
     * @param string $type
     * @return array
     */
    public function getBotsFromDatabase(int $botMotherId, string $type): array
    {
        try {
            Log::info('Getting Quran bots from database', [
                'bot_mother_id' => $botMotherId,
                'type' => $type,
            ]);
            
            // دریافت زبان‌های منحصر به فرد از BotLog برای ربات‌های قرآن
            // بدون استفاده از bot_id (چون در لاگ‌های قدیمی درست نیست)
            $quranLanguages = BotLog::where('bot_mother_id', $botMotherId)
                ->where('type', $type)
                ->where('webhook_endpoint_uri', 'webhook-quran-word')
                ->whereNotNull('language')
                ->distinct('language')
                ->pluck('language')
                ->toArray();
            
            if (empty($quranLanguages)) {
                Log::warning('No Quran bot languages found', [
                    'bot_mother_id' => $botMotherId,
                    'type' => $type,
                ]);
                return [];
            }
            
            Log::info('Quran bot languages found', [
                'bot_mother_id' => $botMotherId,
                'type' => $type,
                'languages' => $quranLanguages,
            ]);
            
            // دریافت ربات‌های قرآن از جدول bots
            $query = Bot::where('bot_mother_id', $botMotherId);
            
            // اضافه کردن شرط‌های نوع ربات
            if ($type == 'telegram') {
                $query->whereNotNull('telegram_bot_token')
                    ->where('telegram_bot_token', '!=', '')
                    ->whereNotNull('telegram_bot_name');
            } elseif ($type == 'bale') {
                $query->whereNotNull('bale_bot_token')
                    ->where('bale_bot_token', '!=', '')
                    ->whereNotNull('bale_bot_name');
            }
            
            $bots = $query->get();
            
            Log::info('Bots query result', [
                'bot_mother_id' => $botMotherId,
                'type' => $type,
                'found_count' => $bots->count(),
                'bot_ids_found' => $bots->pluck('id')->toArray(),
            ]);
            
            if ($bots->isEmpty()) {
                Log::warning('No bots found in database', [
                    'bot_mother_id' => $botMotherId,
                    'type' => $type,
                ]);
                return [];
            }
            
            // ساخت map از language به bot
            // برای هر زبان، ربات مربوطه را پیدا می‌کنیم
            $languageBotMap = [];
            
            foreach ($quranLanguages as $language) {
                // پیدا کردن ربات مربوط به این زبان
                // از آنجایی که نمی‌توانیم مستقیماً match کنیم، باید از bot_id استفاده کنیم
                // اما bot_id در لاگ‌های قدیمی درست نیست، پس باید از روش دیگری استفاده کنیم
                
                // راه حل: برای هر زبان، رباتی را پیدا کنیم که:
                // 1. در BotLog برای این زبان و type و webhook_endpoint_uri لاگ دارد
                // 2. bot_id آن در bots موجود است (حتی اگر درست نباشد)
                
                // ابتدا bot_id های مربوط به این زبان را از BotLog بگیریم
                $botIdsForLanguage = BotLog::where('bot_mother_id', $botMotherId)
                    ->where('type', $type)
                    ->where('webhook_endpoint_uri', 'webhook-quran-word')
                    ->where('language', $language)
                    ->distinct('bot_id')
                    ->pluck('bot_id')
                    ->toArray();
                
                // پیدا کردن ربات مربوطه
                $botForLanguage = null;
                
                // اول سعی می‌کنیم از bot_id استفاده کنیم
                if (!empty($botIdsForLanguage)) {
                    foreach ($botIdsForLanguage as $botId) {
                        $bot = $bots->firstWhere('id', $botId);
                        if ($bot) {
                            $botForLanguage = $bot;
                            break;
                        }
                    }
                }
                
                // اگر ربات پیدا نشد، از اولین ربات استفاده می‌کنیم
                // (این یک fallback است برای زمانی که bot_id درست نیست)
                if (!$botForLanguage && !$bots->isEmpty()) {
                    // سعی می‌کنیم رباتی را پیدا کنیم که token دارد
                    foreach ($bots as $bot) {
                        if (($type == 'telegram' && $bot->telegram_bot_token) || 
                            ($type == 'bale' && $bot->bale_bot_token)) {
                            $botForLanguage = $bot;
                            break;
                        }
                    }
                }
                
                if ($botForLanguage) {
                    $languageBotMap[$language] = $botForLanguage;
                }
            }
            
            // ساخت نتیجه نهایی
            $resultBots = [];
            
            foreach ($languageBotMap as $language => $bot) {
                // تشخیص نام ربات
                $botName = null;
                if ($type == 'telegram' && $bot->telegram_bot_name) {
                    $botName = $bot->telegram_bot_name;
                } elseif ($type == 'bale' && $bot->bale_bot_name) {
                    $botName = $bot->bale_bot_name;
                }
                
                if (!$botName) {
                    continue;
                }
                
                // ساخت لینک
                $link = $this->buildBotLink($botName, $type);
                
                // دریافت نام زبان
                $languageName = $this->getLanguageDisplayName($language, 'fa');
                
                $resultBots[] = [
                    'language_code' => $language,
                    'language_name' => $languageName,
                    'link' => $link,
                    'description' => null,
                ];
            }
            
            Log::info('Quran bots from database', [
                'bot_mother_id' => $botMotherId,
                'type' => $type,
                'count' => count($resultBots),
                'languages' => array_keys($languageBotMap),
            ]);
            
            return $resultBots;
        } catch (\Exception $e) {
            Log::error('Error getting Quran bots from database', [
                'error' => $e->getMessage(),
                'bot_mother_id' => $botMotherId,
                'type' => $type,
            ]);
            return [];
        }
    }

    /**
     * ترکیب ربات‌های کانفیگ و دیتابیس
     * 
     * @param array $configBots
     * @param array $dbBots
     * @return array
     */
    public function mergeBots(array $configBots, array $dbBots): array
    {
        $merged = [];
        $languageCodes = [];

        // اول ربات‌های کانفیگ را اضافه می‌کنیم
        foreach ($configBots as $bot) {
            $languageCode = $bot['language_code'];
            $merged[] = $bot;
            $languageCodes[] = $languageCode;
        }

        // سپس ربات‌های دیتابیس را اضافه می‌کنیم (فقط اگر در کانفیگ نبودند)
        foreach ($dbBots as $bot) {
            $languageCode = $bot['language_code'];
            if (!in_array($languageCode, $languageCodes)) {
                $merged[] = $bot;
                $languageCodes[] = $languageCode;
            }
        }

        return $merged;
    }

    /**
     * تولید متن معرفی ربات‌های قرآن
     * 
     * @param string $language
     * @param string $source
     * @param int|null $botMotherId
     * @param string|null $type
     * @return string
     */
    public function generateIntroductionMessage(
        string $language,
        string $source = 'both',
        ?int $botMotherId = null,
        ?string $type = null
    ): string {
        Log::info('generateIntroductionMessage called', [
            'language' => $language,
            'source' => $source,
            'bot_mother_id' => $botMotherId,
            'type' => $type,
        ]);
        
        // تنظیم زبان برای ترجمه
        App::setLocale($language);

        // دریافت ربات‌ها
        $configBots = $this->getBotsFromConfig();
        Log::info('Config bots count', ['count' => count($configBots)]);
        
        $dbBots = [];
        
        if ($source == 'both' || $source == 'database_only') {
            if ($botMotherId && $type) {
                $dbBots = $this->getBotsFromDatabase($botMotherId, $type);
                Log::info('Database bots count', ['count' => count($dbBots)]);
            } else {
                Log::warning('Missing parameters for database query', [
                    'bot_mother_id' => $botMotherId,
                    'type' => $type,
                ]);
            }
        }

        // ترکیب یا انتخاب منبع
        if ($source == 'both') {
            $bots = $this->mergeBots($configBots, $dbBots);
        } elseif ($source == 'database_only') {
            $bots = $dbBots;
        } else {
            $bots = $configBots;
        }
        
        Log::info('Final bots count', ['count' => count($bots), 'source' => $source]);

        // تولید متن
        $message = $this->buildMessage($bots, $language);

        return $message;
    }

    /**
     * Helper method برای ترجمه با error handling
     * 
     * @param string $key
     * @param array $replace
     * @param string $locale
     * @param string|null $fallback
     * @return string
     */
    private function safeTrans(string $key, array $replace = [], string $locale = 'fa', ?string $fallback = null): string
    {
        try {
            $result = trans($key, $replace, $locale);
            // اگر ترجمه پیدا نشد (key برگردانده شد)
            if ($result == $key) {
                return $fallback ?? $key;
            }
            return $result;
        } catch (\ParseError $e) {
            // خطای syntax در فایل ترجمه
            Log::error('ParseError in translation file', [
                'key' => $key,
                'locale' => $locale,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $fallback ?? $key;
        } catch (\Exception $e) {
            // سایر خطاها
            Log::warning('Error loading translation', [
                'key' => $key,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);
            return $fallback ?? $key;
        }
    }

    /**
     * ساخت پیام کامل
     * 
     * @param array $bots
     * @param string $language
     * @return string
     */
    private function buildMessage(array $bots, string $language): string
    {
        $count = count($bots);
        
        // اگر هیچ رباتی نیست، پیام مناسب برگردان
        if ($count == 0) {
            return "❌ هیچ ربات قرآنی یافت نشد.";
        }
        
        // عنوان
        $title = $this->safeTrans(
            'bot.quran_bots.intro.title',
            [],
            $language,
            config('quran_bots.intro_text.title', '✨ مژده به پویندگان راه قرآن ✨')
        );

        // متن اصلی
        $body = $this->safeTrans(
            'bot.quran_bots.intro.body',
            ['count' => $count],
            $language,
            str_replace('{count}', $count, config('quran_bots.intro_text.body', ''))
        );

        // معرفی ربات‌ها
        $botsIntro = $this->safeTrans(
            'bot.quran_bots.intro.bots_intro',
            [],
            $language,
            config('quran_bots.intro_text.bots_intro', '')
        );

        $botsTitle = $this->safeTrans(
            'bot.quran_bots.intro.bots_title',
            [],
            $language,
            config('quran_bots.intro_text.bots_title', '')
        );

        // لیست ربات‌ها
        $botList = $this->formatBotList($bots, $language);

        // کانال‌ها
        $channelsIntro = $this->safeTrans(
            'bot.quran_bots.intro.channels_intro',
            [],
            $language,
            config('quran_bots.intro_text.channels_intro', '')
        );

        $channels = $this->formatChannels($language);

        // ترکیب پیام
        $message = $title . "\n\n";
        $message .= $body . "\n\n";
        $message .= $botsIntro . "\n\n";
        $message .= $botsTitle . "\n";
        $message .= $botList . "\n\n";
        $message .= $channelsIntro . "\n\n";
        $message .= $channels;

        return $message;
    }

    /**
     * فرمت کردن لیست ربات‌ها (هر زبان در یک خط)
     * 
     * @param array $bots
     * @param string $language
     * @return string
     */
    public function formatBotList(array $bots, string $language): string
    {
        $formatted = '';
        $index = 1;

        foreach ($bots as $bot) {
            try {
                $languageName = $this->getLanguageDisplayName($bot['language_code'], $language);
            } catch (\Exception $e) {
                Log::warning('Error getting language display name', [
                    'language_code' => $bot['language_code'],
                    'target_language' => $language,
                    'error' => $e->getMessage(),
                ]);
                $languageName = $bot['language_code'];
            }
            
            $link = $bot['link'];
            $description = $bot['description'] ?? '';

            $formatted .= "{$index}. {$languageName}: {$link}";
            if ($description) {
                $formatted .= " {$description}";
            }
            $formatted .= "\n";
            $index++;
        }

        return trim($formatted);
    }

    /**
     * فرمت کردن کانال‌ها
     * 
     * @param string $language
     * @return string
     */
    private function formatChannels(string $language): string
    {
        $channels = config('quran_bots.channels', []);
        $formatted = '';

        if (isset($channels['eitaa'])) {
            $formatted .= "✅ ایتا: {$channels['eitaa']}\n";
        }
        if (isset($channels['bale'])) {
            $formatted .= "✅ بله: {$channels['bale']}\n";
        }
        if (isset($channels['telegram'])) {
            $formatted .= "✅ تلگرام: {$channels['telegram']}\n";
        }

        return trim($formatted);
    }

    /**
     * دریافت نام نمایشی زبان
     * 
     * @param string $languageCode
     * @param string $targetLanguage
     * @return string
     */
    private function getLanguageDisplayName(string $languageCode, string $targetLanguage): string
    {
        // تلاش برای دریافت از ترجمه
        $key = "bot.quran_bots.language_name.{$languageCode}";
        $name = $this->safeTrans($key, [], $targetLanguage);
        
        if ($name != $key) {
            return $name;
        }

        // اگر ترجمه نبود، از کانفیگ بگیر
        try {
            $bots = config('quran_bots.bots', []);
            foreach ($bots as $bot) {
                if ($bot['language_code'] == $languageCode) {
                    return $bot['language_name'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error getting language name from config', [
                'language_code' => $languageCode,
                'error' => $e->getMessage(),
            ]);
        }

        // اگر هیچکدام نبود، کد زبان را برگردان
        return $languageCode;
    }

    /**
     * ساخت لینک ربات
     * 
     * @param string $username
     * @param string $type
     * @return string
     */
    private function buildBotLink(string $username, string $type = 'telegram'): string
    {
        if ($type == 'telegram') {
            return "t.me/{$username}";
        } elseif ($type == 'bale') {
            return "ble.ir/{$username}";
        }
        
        return $username;
    }
}
