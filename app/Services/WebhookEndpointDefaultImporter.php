<?php

namespace App\Services;

use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;

class WebhookEndpointDefaultImporter
{
    /**
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function import(bool $force = false): array
    {
        $endpoints = $this->getDefaultEndpoints();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            foreach ($endpoints as $endpoint) {
                try {
                    $endpointId = $endpoint['id'];
                    $existingEndpoint = WebhookEndpoint::where('endpoint_id', $endpointId)->first();

                    if ($existingEndpoint) {
                        if (!$force) {
                            $skipped++;
                            continue;
                        }

                        $existingEndpoint->update($this->endpointAttributes($endpoint));
                        $updated++;
                    } else {
                        WebhookEndpoint::create($this->endpointAttributes($endpoint));
                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    public function getDefaultEndpoints(): array
    {
        return [
            [
                'id' => 'webhook-personnel-registration',
                'name' => 'ثبت‌نام پرسنل',
                'route' => '/api/webhook-personnel-registration',
                'description' => 'ربات ثبت‌نام پرسنل',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => true,
            ],
            [
                'id' => 'webhook-personnel-admin',
                'name' => 'ادمین ثبت‌نام پرسنل',
                'route' => '/api/webhook-personnel-admin',
                'description' => 'ربات ادمین برای مشاهده لیست ثبت‌نام‌های پرسنل',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-mission-bot',
                'name' => 'ربات ماموریت',
                'route' => '/api/webhook-mission-bot',
                'description' => 'ربات مدیریت ماموریت‌ها',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-mission-media',
                'name' => 'ربات مدیا ماموریت',
                'route' => '/api/webhook-mission-media',
                'description' => 'ربات مدیریت و آپلود مدیاهای آموزشی ماموریت‌ها',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-task-approval',
                'name' => 'تایید وظایف',
                'route' => '/api/webhook-task-approval',
                'description' => 'ربات تایید و رد وظایف',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-weather',
                'name' => 'آب و هوا',
                'route' => '/api/webhook-weather',
                'description' => 'ربات اطلاع از آب و هوا',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-quran-word',
                'name' => 'کامل قرآن مرور ختم و حفظ شماره 7',
                'route' => '/api/webhook-quran-word',
                'description' => 'ربات کامل قرآن مرور ختم و حفظ شماره 7',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => true,
                'supports_multiple_languages' => true,
            ],
            [
                'id' => 'webhook-quran-ayat',
                'name' => 'جستجوی آیات قرآن',
                'route' => '/api/webhook-quran-ayat',
                'description' => 'ربات جستجوی آیات قرآن',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-hadith',
                'name' => 'حدیث',
                'route' => '/api/webhook-hadith',
                'description' => 'ربات جستجوی احادیث',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-nahj',
                'name' => 'نهج البلاغه',
                'route' => '/api/webhook-nahj',
                'description' => 'ربات جستجوی نهج البلاغه',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-blog',
                'name' => 'وبلاگ',
                'route' => '/api/webhook-blog',
                'description' => 'ربات مدیریت وبلاگ',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-rss',
                'name' => 'RSS Feed',
                'route' => '/api/webhook-rss',
                'description' => 'ربات RSS Feed',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-bot-children',
                'name' => 'ربات‌های فرزند',
                'route' => '/api/webhook-bot-children',
                'description' => 'ربات مدیریت ربات‌های فرزند',
                'requires_bot_mother_id' => false,
                'requires_token' => false,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-presenter-bot',
                'name' => 'ربات پرزنتر',
                'route' => '/api/webhook-presenter-bot',
                'description' => 'ربات ارائه دهنده - ارسال متن‌های آماده به ترتیب',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-rating-bot',
                'name' => 'ربات امتیازدهی (نظر سنجی)',
                'route' => '/api/webhook-rating-bot',
                'description' => 'ربات نظر سنجی - ارسال عبارت‌ها و دریافت امتیاز ۱ تا ۵ از کاربر',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-psychology-test',
                'name' => 'تست روانشناسی',
                'route' => '/api/webhook-psychology-test',
                'description' => 'ربات تست روانشناسی - سوالات 5 گزینه‌ای با دسته‌بندی و محاسبه امتیازات',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'admin-daily-channel',
                'name' => 'ربات ادمین کانال (تک‌آیه/حدیث/نهج/شراب بهشتی)',
                'route' => '/api/webhook-bot-mother',
                'description' => 'هر ۲۴ ساعت یک آیه یا حدیث یا نهج یا شراب بهشتی در کانال/گروه ارسال می‌شود',
                'requires_bot_mother_id' => true,
                'requires_token' => false,
                'requires_language' => false,
            ],
            [
                'id' => 'admin-channel-media-queue',
                'name' => 'ادمین کانال با صف رسانه پویا',
                'route' => '/api/webhook-bot-mother',
                'description' => 'صف مطالب (متن/عکس/ویدیو) بساز و به ترتیب به کانال/گروه ارسال کن',
                'requires_bot_mother_id' => true,
                'requires_token' => false,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-list-bot',
                'name' => 'فهرست با دکمه شیشه‌ای',
                'route' => '/api/webhook-list-bot',
                'description' => 'ربات فهرست با منوی درختی و دکمه‌های اینلاین (لینک به ربات/کانال/ایتا/سایت)',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-mp-contact',
                'name' => 'ارتباط با نماینده مجلس',
                'route' => '/api/webhook-mp-contact',
                'description' => 'تیکتینگ مردمی، نظرسنجی موافق/مخالف و پنل ادمین داخل چت با دکمه‌های شیشه‌ای',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-rss-admin',
                'name' => 'ربات ادمین RSS',
                'route' => '/api/webhook-rss-admin',
                'description' => 'ثبت سریع فید RSS با انتخاب تگ — فقط ادمین',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
        ];
    }

    private function endpointAttributes(array $endpoint): array
    {
        return [
            'endpoint_id' => $endpoint['id'],
            'name' => $endpoint['name'],
            'route' => $endpoint['route'],
            'description' => $endpoint['description'] ?? null,
            'requires_bot_mother_id' => $endpoint['requires_bot_mother_id'] ?? false,
            'requires_token' => $endpoint['requires_token'] ?? false,
            'requires_language' => $endpoint['requires_language'] ?? false,
            'supports_multiple_languages' => $endpoint['supports_multiple_languages'] ?? false,
            'is_active' => true,
        ];
    }
}
