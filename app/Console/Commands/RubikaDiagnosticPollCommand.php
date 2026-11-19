<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\Rubika\RubikaUpdateNormalizer;
use App\Services\RubikaBotClient;
use App\Services\SyncPlatformRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * polling دستی ربات تشخیصی روبیکا (BM-RUBIKA-DIAGNOSTIC-BOT).
 *
 * فقط برای تست/عیب‌یابی: getUpdates را یک بار صدا می‌زند، آپدیت‌ها را
 * با همان نرمال‌ساز webhook نمایش می‌دهد و offset بعدی را در کش نگه
 * می‌دارد تا اجرای بعدی از همانجا ادامه دهد.
 *
 * قرارداد offset:
 *  - ورودی:  {--offset=} (اختیاری) یا کش rubika_diag_poll_offset:{bot_id}
 *  - خروجی:  next_offset_id (مستند) / data.next_offset_id (پاکت دفاعی)
 *  - ⚠️ `start_id` در `aux_data` شناسهٔ دکمهٔ کیپد است و offset polling
 *    نیست (مستندات روبیکا) — هرگز به‌عنوان offset مصرف نمی‌شود.
 *  - اگر next_offset_id در پاسخ نباشد، offset کش به‌روزرسانی نمی‌شود
 *    (برای جلوگیری از پرش/تکرار).
 *
 * تعارض Polling/Webhook:
 *  اگر rubika_webhook_is_set = 1 باشد، polling انجام نمی‌شود (آپدیت‌ها
 *  مصرف webhook می‌شوند) — مگر با --force.
 */
class RubikaDiagnosticPollCommand extends Command
{
    protected $signature = 'rubika:diagnostic-poll {bot_id : شناسهٔ بزرگ‌ساختهٔ ربات در جدول bots}
        {--offset= : offset شروع (در صورت نبود، از کش می‌خواند)}
        {--limit=50 : تعداد حداکثری آپدیت در یک فراخوانی}
        {--force : حتی اگر webhook فعال است هم polling کن (مخاطره‌آمیز)}';

    protected $description = 'فراخوانی یک‌بارهٔ getUpdates برای ربات روبیکا — نمایش آپدیت‌های نرمال‌شده و ذخیرهٔ next_offset_id (فقط تست)';

    public function handle(): int
    {
        $botId = (int) $this->argument('bot_id');
        $limit = max(1, (int) $this->option('limit'));

        $bot = Bot::find($botId);
        if ($bot === null) {
            $this->error("رباتی با id=$botId پیدا نشد.");

            return self::FAILURE;
        }

        $token = $bot->{SyncPlatformRegistry::tokenColumn('rubika')} ?? null;
        if (!is_string($token) || $token === '') {
            $this->error('توکن روبیکا برای این ربات ثبت نشده است.');

            return self::FAILURE;
        }

        // تعارض Polling/Webhook — هرگز هردو هم‌زمان بدون بررسی.
        if ((bool) $bot->rubika_webhook_is_set && !$this->option('force')) {
            $this->error('Webhook برای این ربات فعال است (rubika_webhook_is_set=1).');
            $this->line('polling هم‌زمان با webhook برای یک توکن تعارض دارد؛');
            $this->line('اگر با این حال اصرار دارید: --force');

            return self::FAILURE;
        }

        $cacheKey = 'rubika_diag_poll_offset:' . $botId;
        $offset = $this->option('offset')
            ? (string) $this->option('offset')
            : (string) (Cache::get($cacheKey, '') ?? '');

        $client = new RubikaBotClient($token);

        try {
            $response = $client->getUpdates($offset !== '' ? $offset : null, $limit);
        } catch (\Throwable $e) {
            $this->error('فراخوانی getUpdates ناموفق بود: ' . \App\Helpers\TokenHelper::redactTokens($e->getMessage()));

            return self::FAILURE;
        }

        // خوانش دفاعی: responses مستند { updates: [...], next_offset_id: ... }
        // یا پاکت دو سطحی { data: { updates: [...], next_offset_id: ... } }
        $inner = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $updates = is_array($inner['updates'] ?? null) ? $inner['updates'] : [];
        $nextOffset = $inner['next_offset_id'] ?? $inner['nextOffsetId'] ?? null;

        $this->line('آپدیت‌های دریافتی: ' . count($updates));

        foreach ($updates as $i => $update) {
            if (!is_array($update)) {
                continue;
            }

            $n = new RubikaUpdateNormalizer(['update' => $update]);
            $forward = $n->isForwarded()
                ? ' | فوروارد: ' . ($n->forwardedInfo()['from_chat_id'] ?? '?')
                : '';

            $this->line(sprintf(
                '  [%d] type=%s chat_id=%s message_id=%s sender=%s text=%s%s',
                $i + 1,
                $n->type() ?: '?',
                $n->chatId() ?? '?',
                $n->messageId() ?? '?',
                $n->senderId() ?? '?',
                mb_substr((string) ($n->text() ?? ''), 0, 80),
                $forward
            ));
        }

        if ($updates !== []) {
            if ((is_string($nextOffset) && $nextOffset !== '') || (is_int($nextOffset) && $nextOffset > 0)) {
                Cache::put($cacheKey, (string) $nextOffset, 86400);
                $this->line('next_offset_id ذخیره شد: ' . $nextOffset . ' (برای اجرای بعدی)');
            } else {
                $this->warn('next_offset_id در پاسخ یافت نشد — offset کش به‌روزرسانی نشد.');
            }
        }

        $this->line('یادآوری: start_id در aux_data شناسهٔ دکمهٔ کیپد است و offset نیست.');

        return self::SUCCESS;
    }
}
