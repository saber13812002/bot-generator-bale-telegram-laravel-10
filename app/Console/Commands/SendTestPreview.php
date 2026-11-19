<?php

namespace App\Console\Commands;

use App\Services\TestPostPreviewService;
use Illuminate\Console\Command;

/**
 * ارسال پست تستی/پیش‌نمایش (preview) به کانال تستی — از طریق همان متدهای production
 *
 * نمونه‌ها:
 *   php artisan app:send-test-preview --type=text --caption="پیام تستی"
 *   php artisan app:send-test-preview --type=photo --file=storage/app/test-media/photo.png --caption="کپشن عکس"
 *   php artisan app:send-test-preview --type=video --file=URL --caption="کپشن ویدیو"
 *   php artisan app:send-test-preview --type=audio --file=audio.mp3 --caption="کپشن صوت"
 *   php artisan app:send-test-preview --type=photo --platform=bale --token=TOKEN --chat-id=123
 *   php artisan app:send-test-preview --type=text --parse-mode=HTML
 *   php artisan app:send-test-preview --samples   # ساخت فایل‌های نمونه (عکس + صدا) در storage/app/test-media
 */
class SendTestPreview extends Command
{
    protected $signature = 'app:send-test-preview
                            {--type=text : نوع پست: text|photo|audio|music|voice|video}
                            {--caption= : متن پیام (برای text) یا کپشن رسانه}
                            {--file= : مسیر محلی یا URL فایل رسانه (اگر خالی، از دایرکتوری TEST_PREVIEW_MEDIA_DIR گرفته می‌شود)}
                            {--name= : نام فایل برای آپلود (اختیاری)}
                            {--mime= : mime type (اختیاری)}
                            {--platform= : پلتفرم مقصد: eitaa|bale|telegram (پیش‌فرض از .env)}
                            {--token= : توکن ربات (پیش‌فرض TEST_PREVIEW_BOT_TOKEN)}
                            {--chat-id= : chat id مقصد (پیش‌فرض TEST_PREVIEW_CHAT_ID)}
                            {--parse-mode= : HTML|Markdown فقط برای text}
                            {--samples : ساخت فایل‌های نمونه (PNG + WAV) و پایان}';

    protected $description = 'ارسال پست تستی/پیش‌نمایش (متن/عکس/صوت/ویدیو با کپشن) به کانال تستی — از همان متدهای production';

    public function handle(TestPostPreviewService $service): int
    {
        if ($this->option('samples')) {
            $files = $service->makeSampleMedia();
            foreach ($files as $f) {
                $this->info('created: ' . $f);
            }
            if (empty($files)) {
                $this->comment('فایل‌های نمونه از قبل موجود هستند (یا GD برای PNG فعال نیست).');
            }
            $this->line('دایرکتوری رسانه‌ها: ' . storage_path('app/test-media'));
            $this->line('حالا می‌توانی بفرستی، مثلا: php artisan app:send-test-preview --type=photo');

            return self::SUCCESS;
        }

        $type = (string) $this->option('type');

        $result = $service->sendPreview(
            $type,
            $this->option('caption') ? (string) $this->option('caption') : null,
            $this->option('file') ? (string) $this->option('file') : null,
            $this->option('platform') ? (string) $this->option('platform') : null,
            $this->option('token') ? (string) $this->option('token') : null,
            $this->option('chat-id') ? (string) $this->option('chat-id') : null,
            $this->option('name') ? (string) $this->option('name') : null,
            $this->option('mime') ? (string) $this->option('mime') : null,
            $this->option('parse-mode') ? (string) $this->option('parse-mode') : null
        );

        if ($result['success']) {
            $this->info("✅ پست تستی ارسال شد ({$type}) — message_id: " . ($result['message_id'] ?? '—'));

            return self::SUCCESS;
        }

        $this->error('❌ ارسال پست تستی ناموفق بود: ' . ($result['error'] ?? 'unknown error'));
        $this->comment('نکات عیب‌یابی:');
        $this->comment('  - TEST_PREVIEW_PLATFORM / TEST_PREVIEW_BOT_TOKEN / TEST_PREVIEW_CHAT_ID را در .env تنظیم کن');
        $this->comment('  - ربات باید عضو کانال تستی باشد (و در ایتا admin/creator)');
        $this->comment('  - برای رسانه می‌توانی --samples را برای ساخت فایل نمونه بزنی');

        return self::FAILURE;
    }
}
