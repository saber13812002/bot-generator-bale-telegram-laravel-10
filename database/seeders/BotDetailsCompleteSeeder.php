<?php

namespace Database\Seeders;

use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class BotDetailsCompleteSeeder extends Seeder
{
    public function run(): void
    {
        // ===== QURAN BOT =====
        $this->updateDetails('quran-bot', [
            'icon_emoji' => '📖',
            'detailed_description' => "ربات قرآن کریم یک ابزار کامل برای مطالعه، حفظ، ختم و جستجو در قرآن کریم است.\n\n"
                . "✅ مطالعه آیه به آیه با ترجمه و تفسیر\n"
                . "✅ فایل صوتی قرائت عربی و فارسی\n"
                . "✅ جستجوی پیشرفته در کل قرآن\n"
                . "✅ سیستم حفظ و تکرار هوشمند\n"
                . "✅ ختم قرآن در بازه زمانی دلخواه\n"
                . "✅ نمایش صفحه اسکن شده قرآن\n"
                . "✅ پشتیبانی از ۱۸ زبان زنده دنیا",
            'features' => [
                'مطالعه آیه به آیه' => 'متن عربی + ترجمه + تفسیر',
                'فایل صوتی' => 'قرائت عربی و ترجمه فارسی',
                'جستجو' => 'فول‌تکست در کل قرآن',
                'زبان‌ها' => '۱۸ زبان زنده دنیا',
                'حفظ قرآن' => 'سیستم تکرار و مرور هوشمند',
                'ختم قرآن' => 'برنامه‌ریزی شخصی',
            ],
            'usage_instructions' => "۱. /start را بزنید\n"
                . "۲. یک سوره را از لیست ۱۱۴ سوره انتخاب کنید\n"
                . "۳. شماره آیه را بفرستید یا از دکمه‌ها استفاده کنید\n"
                . "۴. گزینه‌های نمایش: متن، ترجمه، صوت، تفسیر\n"
                . "۵. برای جستجو: /search کلمه مورد نظر\n"
                . "۶. برای حفظ: /memorize",
            'technical_details' => "API: Quran.com API + خودی\n"
                . "فرمت صوت: MP3 (۳۲kbps)\n"
                . "پشتیبانی از: Telegram, Bale\n"
                . "دیتابیس: MySQL + Laravel Fulltext",
            'sample_telegram_link' => 'https://t.me/quran_sample_bot',
            'sample_bale_link' => 'https://ble.ir/quran_sample_bot',
            'blog_medium_link' => 'https://saber-tabatabaee.medium.com/holy-book-project-quran-telegram-bot',
            'blog_virgool_link' => 'https://vrgl.ir/hp4xr',
            'image_path' => 'images/bots/quran-bot.png',
        ]);

        // ===== WEATHER BOT =====
        $this->updateDetails('weather-bot', [
            'icon_emoji' => '🌤️',
            'detailed_description' => "ربات هواشناسی با قابلیت پیش‌بینی ۱۶ ساعته و هشدارهای خودکار.\n\n"
                . "✅ پیش‌بینی دمای هوا\n"
                . "✅ هشدار تغییرات دما و سرعت باد\n"
                . "✅ هشدار باران و برف\n"
                . "✅ ارسال خودکار به گروه",
            'features' => [
                'پیش‌بینی' => '۱۶ ساعت آینده',
                'هشدار دما' => 'قابل تنظیم',
                'هشدار باد' => 'قابل تنظیم',
                'ارسال گروهی' => 'خودکار به گروه',
            ],
            'usage_instructions' => "۱. /start را بزنید\n"
                . "۲. نام شهر را بفرستید\n"
                . "۳. پیش‌بینی نمایش داده می‌شود\n"
                . "۴. برای تنظیم هشدار: /alert",
            'image_path' => 'images/bots/weather-bot.png',
        ]);

        // ===== MAWKIB FINDER =====
        $this->updateDetails('mawkib-finder', [
            'icon_emoji' => '🕋',
            'detailed_description' => "ربات موکب‌یاب اربعین - با احراز هویت از طریق OTP بله.\n\n"
                . "✅ احراز هویت با شماره موبایل (OTP)\n"
                . "✅ انتخاب استان محل سکونت\n"
                . "✅ انتخاب تاریخ ورود به عراق\n"
                . "✅ انتخاب مدت اقامت\n"
                . "✅ نمایش موکب‌های موجود با ظرفیت",
            'features' => [
                'احراز هویت' => 'OTP پیامرسان بله',
                'انتخاب استان' => '۳۱ استان',
                'انتخاب تاریخ' => '۱۴ روز آینده',
                'مدت اقامت' => '۱ تا ۱۴ روز',
                'ظرفیت موکب' => 'نمایش实时',
            ],
            'usage_instructions' => "۱. /start را بزنید\n"
                . "۲. شماره موبایل را وارد کنید\n"
                . "۳. کد OTP دریافتی را وارد کنید\n"
                . "۴. کد ملی ۱۰ رقمی را وارد کنید\n"
                . "۵. استان را انتخاب کنید\n"
                . "۶. تاریخ ورود را انتخاب کنید\n"
                . "۷. مدت اقامت را انتخاب کنید\n"
                . "۸. نتایج موکب‌ها نمایش داده می‌شود",
            'image_path' => 'images/bots/mawkib-finder.png',
        ]);

        // ===== PRAYER BOT =====
        $this->updateDetails('prayer-bot', [
            'icon_emoji' => '🕌',
            'detailed_description' => "ثبت و پیگیری رکعات نماز قضا با تشخیص هوشمند نوع نماز.\n\n"
                . "✅ ثبت رکعات با ۲، ۳، ۴\n"
                . "✅ تشخیص هوشمند نوع نماز\n"
                . "✅ گزارش هفتگی ایمیلی\n"
                . "✅ صفحه وب گزارش\n"
                . "✅ ثبت تخمین نمازهای قضا",
            'features' => [
                'ثبت رکعات' => '۲، ۳، ۴ رکعتی',
                'تشخیص هوشمند' => 'نوع نماز خودکار',
                'گزارش' => 'هفتگی ایمیلی',
                'صفحه وب' => 'مشاهده آنلاین',
                'تخمین' => 'ثبت تخمین قضا',
            ],
            'usage_instructions' => "۱. /start را بزنید\n"
                . "۲. تعداد رکعت را بفرستید (۲، ۳، ۴)\n"
                . "۳. ربات نوع نماز را تشخیص می‌دهد\n"
                . "۴. برای گزارش هفتگی ایمیل ثبت کنید\n"
                . "۵. /report برای مشاهده آمار",
            'image_path' => 'images/bots/prayer-bot.png',
        ]);

        // ===== PRESENTER BOT =====
        $this->updateDetails('presenter-bot', [
            'icon_emoji' => '🎤',
            'detailed_description' => "ربات ارائه دهنده محتوا به ترتیب - مناسب برای دوره‌های آموزشی و ارائه مطالب.",
            'features' => [
                'ارسال ترتیبی' => 'متن/عکس/ویدیو',
                'کنترل کاربر' => 'با دکمه بعدی/قبلی',
                'پشتیبانی' => 'متن، عکس، ویدیو، صوت',
            ],
            'usage_instructions' => "۱. پس از ساخت ربات، محتوا را وارد کنید\n"
                . "۲. کاربر /start می‌زند\n"
                . "۳. آیتم اول نمایش داده می‌شود\n"
                . "۴. /next برای بعدی",
            'image_path' => 'images/bots/presenter-bot.png',
        ]);

        // ===== RATING BOT =====
        $this->updateDetails('rating-bot', [
            'icon_emoji' => '⭐',
            'detailed_description' => "ربات نظر سنجی و امتیازدهی - ارسال عبارت‌ها و دریافت امتیاز ۱ تا ۵.",
            'features' => [
                'نظر سنجی' => 'امتیاز ۱ تا ۵',
                'گزارش' => 'ذخیره نتایج',
                'پشتیبانی' => 'متن، عکس',
            ],
            'usage_instructions' => "۱. پس از ساخت، عبارت‌ها را وارد کنید\n"
                . "۲. کاربر /start می‌زند\n"
                . "۳. امتیاز ۱ تا ۵ انتخاب می‌کند",
            'image_path' => 'images/bots/rating-bot.png',
        ]);

        // ===== PSYCHOLOGY TEST =====
        $this->updateDetails('psychology-test', [
            'icon_emoji' => '🧠',
            'detailed_description' => "ربات تست روانشناسی با سوالات ۵ گزینه‌ای، دسته‌بندی و محاسبه امتیازات.",
            'features' => [
                'سوالات' => '۵ گزینه‌ای',
                'دسته‌بندی' => 'دسته‌های مختلف',
                'محاسبه' => 'امتیاز خودکار',
                'نتیجه' => 'نمایش نتیجه تست',
            ],
            'usage_instructions' => "۱. /start را بزنید\n"
                . "۲. تست را انتخاب کنید\n"
                . "۳. به سوالات پاسخ دهید\n"
                . "۴. نتیجه نمایش داده می‌شود",
            'image_path' => 'images/bots/psychology-test.png',
        ]);

        // ===== CONTENT SUBMISSION =====
        $this->updateDetails('content-submission', [
            'icon_emoji' => '📝',
            'detailed_description' => "دریافت محتوا (متن/عکس/فیلم) از کاربران، تایید در گروه، انتشار در کانال.",
            'features' => [
                'دریافت محتوا' => 'متن/عکس/ویدیو',
                'تایید گروهی' => 'ریپلای ۱',
                'انتشار' => 'خودکار در کانال',
                'گزارش' => 'به فرستنده',
            ],
            'usage_instructions' => "۱. کاربر در پیوی محتوا می‌فرستد\n"
                . "۲. به گروه تایید ارسال می‌شود\n"
                . "۳. ادمین ریپلای «۱» می‌زند\n"
                . "۴. در کانال منتشر می‌شود",
            'image_path' => 'images/bots/content-submission.png',
        ]);

        // ===== HADITH BOT =====
        $this->updateDetails('hadith-bot', [
            'icon_emoji' => '📜',
            'detailed_description' => "جستجو و مطالعه احادیث شیعه از کتب معتبر با جستجوی فول‌تکست.",
            'features' => [
                'جستجو' => 'فول‌تکست',
                'منابع' => 'کتب معتبر شیعه',
                'سند' => 'نمایش سلسله سند',
                'تصادفی' => 'حدیث تصادفی',
            ],
            'usage_instructions' => "۱. /start را بزنید\n"
                . "۲. کلمه جستجو را بفرستید\n"
                . "۳. نتایج نمایش داده می‌شود\n"
                . "۴. شماره را بفرستید برای متن کامل\n"
                . "۵. /random برای حدیث تصادفی",
            'image_path' => 'images/bots/hadith-bot.png',
        ]);

        // ===== NAHJ BOT =====
        $this->updateDetails('nahj-bot', [
            'icon_emoji' => '📚',
            'detailed_description' => "مطالعه و جستجو در متن کامل نهج البلاغه شامل خطبه‌ها، نامه‌ها و حکمت‌ها.",
            'features' => [
                'خطبه‌ها' => 'جستجو در خطبه‌ها',
                'نامه‌ها' => 'نامه‌های امام علی(ع)',
                'حکمت‌ها' => 'حکمت‌های کوتاه',
                'جستجو' => 'فول‌تکست',
            ],
            'usage_instructions' => "۱. /start را بزنید\n"
                . "۲. جستجو کنید یا از فهرست انتخاب کنید\n"
                . "۳. خطبه/نامه/حکمت را مطالعه کنید",
            'image_path' => 'images/bots/nahj-bot.png',
        ]);

        // ===== LIST BOT =====
        $this->updateDetails('list-bot', [
            'icon_emoji' => '📋',
            'detailed_description' => "ایجاد منوی درختی با دکمه‌های اینلاین شامل لینک به ربات‌ها، کانال‌ها و سایت‌ها.",
            'features' => [
                'منوی درختی' => 'نامحدود سطح',
                'لینک' => 'به ربات/کانال/سایت',
                'دکمه' => 'دکمه شیشه‌ای',
            ],
            'usage_instructions' => "۱. پس از ساخت، منو را با فرمت وارد کنید\n"
                . "۲. - عنوان مادر\n"
                . "۳. -- زیرمنو: لینک\n"
                . "۴. کاربر /start می‌زند",
            'image_path' => 'images/bots/list-bot.png',
        ]);

        Log::info('✅ Bot details seeded successfully');
    }

    private function updateDetails(string $endpointId, array $data): void
    {
        $endpoint = WebhookEndpoint::where('endpoint_id', $endpointId)->first();

        if (!$endpoint) {
            Log::warning("Endpoint not found: {$endpointId}");
            return;
        }

        $endpoint->update($data);
        $this->command->info("✅ Details updated for: {$endpointId}");
    }
}
