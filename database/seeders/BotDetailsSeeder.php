<?php

namespace Database\Seeders;

use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BotDetailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 در حال اضافه کردن توضیحات و ربات‌های مرتبط...');

        // تعریف توضیحات و ربات‌های مرتبط برای هر ربات
        $botDetails = [
            'get-chat-id' => [
                'detailed_description' => 'ربات Get Chat ID یک ابزار ساده و کاربردی است که به شما امکان می‌دهد شناسه چت خود، کانال‌ها و گروه‌ها را دریافت کنید. این ربات برای توسعه‌دهندگان و مدیران کانال‌ها بسیار مفید است.',
                'features' => [
                    'دریافت شناسه چت' => 'نمایش شناسه چت فعلی (خصوصی، گروه، کانال)',
                    'استخراج اطلاعات پیام فوروارد' => 'استخراج شناسه کانال/گروه از پیام‌های فوروارد شده',
                    'پشتیبانی چند پلتفرم' => 'پشتیبانی از تلگرام، بله و گپ',
                ],
                'usage_instructions' => "1. ربات را باز کنید\n2. دستور /start را ارسال کنید\n3. شناسه چت شما نمایش داده می‌شود\n4. برای دریافت شناسه کانال/گروه، یک پیام از آن کانال/گروه را فوروارد کنید",
                'related_bots' => ['webhook-weather', 'webhook-bot-children'],
            ],
            'prayer-bot' => [
                'detailed_description' => 'ربات نماز قضا یک سیستم کامل برای ثبت و پیگیری نمازهای قضای شماست. این ربات با تشخیص هوشمند نوع نماز، امکان ثبت رکعات، مشاهده آمار و دریافت گزارش ایمیلی هفتگی را فراهم می‌کند.',
                'features' => [
                    'ثبت رکعات' => 'ثبت ساده رکعات با ارسال عدد (2، 3 یا 4)',
                    'تشخیص هوشمند' => 'تشخیص خودکار نوع نماز بر اساس زمان و تعداد رکعات',
                    'گزارش ایمیلی' => 'ارسال خودکار گزارش هفتگی به ایمیل',
                    'مدیریت رکعات' => 'امکان حذف و ویرایش رکعات ثبت شده',
                ],
                'usage_instructions' => "2 → ثبت 2 رکعت صبح\n3 → ثبت 3 رکعت مغرب\n4 → ثبت 4 رکعت (ظهر/عصر/عشا - بر اساس زمان)\n/stats → مشاهده آمار\n/email → تنظیم ایمیل",
                'related_bots' => ['webhook-quran-word', 'webhook-hadith', 'webhook-nahj'],
            ],
            'webhook-rss' => [
                'detailed_description' => 'ربات RSS Feed امکان دریافت و نمایش آخرین مطالب از فیدهای RSS مختلف را فراهم می‌کند. این ربات برای دنبال کردن وبلاگ‌ها، خبرگزاری‌ها و سایت‌های مختلف بسیار مفید است.',
                'features' => [
                    'پشتیبانی از RSS' => 'دریافت و نمایش مطالب از فیدهای RSS',
                    'به‌روزرسانی خودکار' => 'بررسی و به‌روزرسانی خودکار فیدها',
                    'فرمت‌های مختلف' => 'پشتیبانی از فرمت‌های مختلف RSS',
                ],
                'related_bots' => ['webhook-blog', 'webhook-presenter-bot'],
            ],
            'webhook-weather' => [
                'detailed_description' => 'ربات آب و هوا اطلاعات دقیق و به‌روز آب و هوای شهرهای مختلف را در اختیار شما قرار می‌دهد. این ربات با استفاده از API های معتبر، پیش‌بینی دقیق آب و هوا را ارائه می‌دهد.',
                'features' => [
                    'اطلاعات دقیق' => 'نمایش دما، رطوبت، سرعت باد و شرایط جوی',
                    'پیش‌بینی چند روزه' => 'پیش‌بینی آب و هوا برای چند روز آینده',
                    'جستجوی شهر' => 'امکان جستجو و انتخاب شهرهای مختلف',
                ],
                'related_bots' => ['get-chat-id', 'webhook-bot-children'],
            ],
            'webhook-personnel-admin' => [
                'detailed_description' => 'ربات ادمین ثبت‌نام پرسنل ابزاری برای مدیران است که امکان مشاهده و مدیریت لیست ثبت‌نام‌های پرسنل را فراهم می‌کند.',
                'features' => [
                    'مشاهده لیست' => 'مشاهده لیست کامل ثبت‌نام‌های پرسنل',
                    'جستجو و فیلتر' => 'امکان جستجو و فیلتر کردن ثبت‌نام‌ها',
                    'مدیریت' => 'امکان تایید یا رد ثبت‌نام‌ها',
                ],
                'related_bots' => ['webhook-personnel-registration', 'webhook-task-approval'],
            ],
            'webhook-task-approval' => [
                'detailed_description' => 'ربات تایید وظایف امکان تایید و رد وظایف را برای مدیران فراهم می‌کند. این ربات برای مدیریت کارهای تیمی و پروژه‌ها بسیار مفید است.',
                'features' => [
                    'تایید/رد وظایف' => 'امکان تایید یا رد وظایف با یک کلیک',
                    'اطلاع‌رسانی' => 'ارسال اطلاع‌رسانی به کاربران',
                    'تاریخچه' => 'ذخیره تاریخچه تغییرات',
                ],
                'related_bots' => ['webhook-mission-bot', 'webhook-personnel-admin'],
            ],
            'webhook-psychology-test' => [
                'detailed_description' => 'ربات تست روانشناسی امکان انجام تست‌های روانشناسی مختلف مانند MBTI را فراهم می‌کند. این ربات با سوالات 5 گزینه‌ای و محاسبه خودکار امتیازات، نتایج دقیق را ارائه می‌دهد.',
                'features' => [
                    'تست‌های متنوع' => 'انواع تست‌های روانشناسی',
                    'سوالات 5 گزینه‌ای' => 'سوالات با 5 گزینه برای دقت بیشتر',
                    'محاسبه خودکار' => 'محاسبه و نمایش نتایج به صورت خودکار',
                    'دسته‌بندی' => 'دسته‌بندی تست‌ها بر اساس موضوع',
                ],
                'related_bots' => ['webhook-weather', 'get-chat-id'],
            ],
            'webhook-personnel-registration' => [
                'detailed_description' => 'ربات ثبت‌نام پرسنل امکان ثبت‌نام و مدیریت اطلاعات پرسنل را فراهم می‌کند. این ربات برای سازمان‌ها و شرکت‌ها بسیار مفید است.',
                'features' => [
                    'ثبت‌نام ساده' => 'فرم ثبت‌نام ساده و کاربردی',
                    'مدیریت اطلاعات' => 'ذخیره و مدیریت اطلاعات پرسنل',
                    'پشتیبانی چند زبانه' => 'پشتیبانی از 15 زبان مختلف',
                ],
                'related_bots' => ['webhook-personnel-admin', 'webhook-task-approval'],
            ],
            'webhook-mission-bot' => [
                'detailed_description' => 'ربات ماموریت ابزاری کامل برای مدیریت ماموریت‌ها و وظایف است. این ربات امکان ایجاد، ویرایش و پیگیری ماموریت‌ها را فراهم می‌کند.',
                'features' => [
                    'مدیریت ماموریت' => 'ایجاد، ویرایش و حذف ماموریت‌ها',
                    'پیگیری' => 'پیگیری وضعیت ماموریت‌ها',
                    'گزارش' => 'گزارش‌گیری از ماموریت‌ها',
                ],
                'related_bots' => ['webhook-mission-media', 'webhook-task-approval'],
            ],
            'webhook-mission-media' => [
                'detailed_description' => 'ربات مدیا ماموریت امکان آپلود و مدیریت فایل‌های آموزشی مربوط به ماموریت‌ها را فراهم می‌کند.',
                'features' => [
                    'آپلود فایل' => 'آپلود انواع فایل‌های آموزشی',
                    'مدیریت' => 'مدیریت و سازماندهی فایل‌ها',
                    'دسترسی' => 'کنترل دسترسی به فایل‌ها',
                ],
                'related_bots' => ['webhook-mission-bot', 'webhook-task-approval'],
            ],
            'webhook-quran-word' => [
                'detailed_description' => 'ربات کامل قرآن مرور ختم و حفظ شماره 7 یک ابزار جامع برای مطالعه، حفظ و مرور قرآن کریم است. این ربات با پشتیبانی از 15 زبان، امکان مطالعه آیه به آیه، جستجو و ترجمه را فراهم می‌کند.',
                'features' => [
                    'مطالعه آیه به آیه' => 'مطالعه قرآن به صورت آیه به آیه',
                    'جستجو' => 'جستجوی پیشرفته در آیات',
                    'حفظ' => 'ابزارهای کمک به حفظ قرآن',
                    'ترجمه' => 'ترجمه به 15 زبان مختلف',
                ],
                'related_bots' => ['webhook-quran-ayat', 'webhook-hadith', 'webhook-nahj'],
            ],
            'webhook-quran-ayat' => [
                'detailed_description' => 'ربات جستجوی آیات قرآن امکان جستجوی سریع و دقیق در آیات قرآن کریم را فراهم می‌کند.',
                'features' => [
                    'جستجوی سریع' => 'جستجوی سریع در تمام آیات',
                    'نتایج دقیق' => 'نمایش نتایج دقیق با شماره سوره و آیه',
                ],
                'related_bots' => ['webhook-quran-word', 'webhook-hadith', 'webhook-nahj'],
            ],
            'webhook-hadith' => [
                'detailed_description' => 'ربات جستجوی احادیث امکان جستجو و مطالعه احادیث معصومین را فراهم می‌کند.',
                'features' => [
                    'جستجوی احادیث' => 'جستجو در مجموعه احادیث',
                    'منابع' => 'نمایش منابع و راویان',
                ],
                'related_bots' => ['webhook-quran-word', 'webhook-nahj', 'prayer-bot'],
            ],
            'webhook-nahj' => [
                'detailed_description' => 'ربات جستجوی نهج البلاغه امکان جستجو و مطالعه خطبه‌ها، نامه‌ها و حکمت‌های نهج البلاغه را فراهم می‌کند.',
                'features' => [
                    'جستجو' => 'جستجو در نهج البلاغه',
                    'دسته‌بندی' => 'دسته‌بندی بر اساس خطبه، نامه و حکمت',
                ],
                'related_bots' => ['webhook-quran-word', 'webhook-hadith', 'prayer-bot'],
            ],
            'webhook-blog' => [
                'detailed_description' => 'ربات مدیریت وبلاگ امکان مدیریت و انتشار مطالب وبلاگ را فراهم می‌کند.',
                'features' => [
                    'مدیریت مطالب' => 'ایجاد، ویرایش و حذف مطالب',
                    'انتشار' => 'انتشار خودکار مطالب',
                ],
                'related_bots' => ['webhook-rss', 'webhook-presenter-bot'],
            ],
            'webhook-bot-children' => [
                'detailed_description' => 'ربات مدیریت ربات‌های فرزند امکان مدیریت و کنترل ربات‌های زیرمجموعه را فراهم می‌کند.',
                'features' => [
                    'مدیریت ربات‌ها' => 'ایجاد و مدیریت ربات‌های فرزند',
                    'کنترل' => 'کنترل دسترسی و تنظیمات',
                ],
                'related_bots' => ['get-chat-id', 'webhook-weather'],
            ],
            'webhook-presenter-bot' => [
                'detailed_description' => 'ربات پرزنتر امکان ارسال متن‌های آماده به ترتیب را فراهم می‌کند. این ربات برای ارائه محتوا و آموزش بسیار مفید است.',
                'features' => [
                    'ارسال ترتیبی' => 'ارسال متن‌ها به ترتیب از پیش تعریف شده',
                    'مدیریت محتوا' => 'مدیریت و سازماندهی محتوا',
                ],
                'related_bots' => ['webhook-blog', 'webhook-rss'],
            ],
            'webhook-rating-bot' => [
                'detailed_description' => 'ربات امتیازدهی (نظر سنجی) عبارت‌ها را به‌صورت ترتیبی نمایش می‌دهد و کاربر برای هر عبارت با ۵ گزینه (۱ تا ۵) میزان موافقت خود را ثبت می‌کند.',
                'features' => [
                    'دکمه‌های ۱ تا ۵' => 'ثبت سریع میزان موافقت/مخالفت برای هر عبارت',
                    'ذخیره پاسخ‌ها' => 'ثبت پاسخ‌ها برای گزارش‌گیری و تحلیل',
                ],
                'related_bots' => ['webhook-presenter-bot', 'webhook-psychology-test'],
            ],
            'webhook-growth-companion' => [
                'detailed_description' => 'رشدیار همراه آرام تأمل روزانه است. حوزه را خودت انتخاب می‌کنی: سلامت، کار، خانواده، مطالعه، معنویت یا هدف شخصی. هر روز یک کارت کوتاه، ثبت حال با چند ضربه، و مرور هفته بدون تشخیص پزشکی. نسخهٔ رایگان برای شروع کافی است؛ رشدیار پرو قفل بودجه و نیمه‌شب را برمی‌دارد.',
                'features' => [
                    'کارت امروز' => 'خلاصهٔ حال، پیشرفت هفته و یک قدم بعدی',
                    'سؤال روزانه' => 'یک سؤال از موضوع باز؛ متن آزاد یا چندگزینه‌ای',
                    'ثبت روزانه' => 'حال، انرژی، خواب و حرکت در چهار ضربه',
                    'مرور هفته' => 'بردها، چالش‌ها و گلوله‌های کوتاه از نوشته‌ها',
                    'رشدیار پرو' => 'موضوعات نامحدود در یک روز، بدون انتظار تا ۳ صبح؛ ماهانه با تخفیف یا سالانه',
                ],
                'usage_instructions' => "1. در bots.pardisania.ir/bots نوع رشدیار را بسازید\n2. در ربات ساخته‌شده /start بزنید و حوزه و شدت را انتخاب کنید\n3. هر روز از کارت امروز یا ثبت روزانه ادامه دهید\n4. موضوعات را از «بیشتر → موضوعات من» مدیریت کنید\n5. اگر به سقف رایگان رسیدید، پرو را از همان چت درخواست کنید",
                'related_bots' => ['webhook-psychology-test', 'book-library'],
            ],
        ];

        foreach ($botDetails as $endpointId => $details) {
            $bot = WebhookEndpoint::where('endpoint_id', $endpointId)->first();
            
            if (!$bot) {
                $this->command->warn("⚠️  ربات با endpoint_id '{$endpointId}' یافت نشد.");
                continue;
            }

            // به‌روزرسانی توضیحات
            $bot->update([
                'detailed_description' => $details['detailed_description'] ?? null,
                'features' => $details['features'] ?? null,
                'usage_instructions' => $details['usage_instructions'] ?? null,
            ]);

            // اضافه کردن ربات‌های مرتبط
            if (isset($details['related_bots']) && is_array($details['related_bots'])) {
                $relatedBots = WebhookEndpoint::whereIn('endpoint_id', $details['related_bots'])
                    ->where('id', '!=', $bot->id)
                    ->get();

                // حذف روابط قبلی
                DB::table('webhook_endpoint_related_bots')
                    ->where('webhook_endpoint_id', $bot->id)
                    ->delete();

                // اضافه کردن روابط جدید
                foreach ($relatedBots as $index => $relatedBot) {
                    DB::table('webhook_endpoint_related_bots')->insert([
                        'webhook_endpoint_id' => $bot->id,
                        'related_webhook_endpoint_id' => $relatedBot->id,
                        'order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->command->info("✅ ربات '{$bot->name}' به‌روزرسانی شد ({$relatedBots->count()} ربات مرتبط)");
            } else {
                $this->command->info("✅ ربات '{$bot->name}' به‌روزرسانی شد");
            }
        }

        $this->command->info('🎉 تمام توضیحات و ربات‌های مرتبط اضافه شدند!');
    }
}
