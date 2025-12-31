<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('Starting ContentSeeder');

        $tenant = Tenant::first();
        if (!$tenant) {
            Log::warning('No tenant found. Please run TenantSeeder first.');
            return;
        }

        $contents = [
            [
                'title' => 'ویدیو آموزشی: نحوه ثبت نام در سیستم',
                'content_type' => 'video',
                'content_url' => 'https://example.com/videos/registration-tutorial.mp4',
                'description' => 'این ویدیو به شما نحوه ثبت نام در سیستم را آموزش می‌دهد.',
                'sort_order' => 1,
            ],
            [
                'title' => 'پی‌دی‌اف: مراحل تکمیل پروفایل کاربری',
                'content_type' => 'pdf',
                'content_url' => 'https://example.com/pdfs/profile-completion-guide.pdf',
                'description' => 'راهنمای گام به گام برای تکمیل پروفایل کاربری خود.',
                'sort_order' => 2,
            ],
            [
                'title' => 'تصویر راهنما: نحوه پر کردن فرم ثبت نام',
                'content_type' => 'image',
                'content_url' => 'https://example.com/images/registration-form-guide.jpg',
                'description' => 'تصویر راهنمای کامل برای پر کردن فرم ثبت نام.',
                'sort_order' => 3,
            ],
            [
                'title' => 'ویدیو آموزشی: مراحل انجام ماموریت و ثبت گزارش',
                'content_type' => 'video',
                'content_url' => 'https://example.com/videos/mission-tutorial.mp4',
                'description' => 'آموزش کامل نحوه انجام ماموریت و ثبت گزارش.',
                'sort_order' => 4,
            ],
            [
                'title' => 'متن آموزشی: نحوه استفاده از ابزارهای آنلاین',
                'content_type' => 'text',
                'content_url' => 'https://example.com/articles/online-tools-guide',
                'description' => 'راهنمای استفاده از ابزارهای آنلاین برای انجام ماموریت.',
                'sort_order' => 5,
            ],
            [
                'title' => 'فایل صوتی: نحوه تایید و رد ماموریت',
                'content_type' => 'audio',
                'content_url' => 'https://example.com/audios/approval-process.mp3',
                'description' => 'توضیحات صوتی درباره فرآیند تایید و رد ماموریت.',
                'sort_order' => 6,
            ],
            [
                'title' => 'تصویر راهنما: ترتیب انجام مراحل ماموریت',
                'content_type' => 'image',
                'content_url' => 'https://example.com/images/mission-steps.jpg',
                'description' => 'نمودار ترتیبی مراحل انجام ماموریت.',
                'sort_order' => 7,
            ],
            [
                'title' => 'ویدیو: آموزش کار با سیستم‌های مدیریتی',
                'content_type' => 'video',
                'content_url' => 'https://example.com/videos/management-systems.mp4',
                'description' => 'آموزش کامل کار با سیستم‌های مدیریتی.',
                'sort_order' => 8,
            ],
            [
                'title' => 'متن توضیحی: چرا باید گزارشات دقیق ارسال کنید',
                'content_type' => 'text',
                'content_url' => 'https://example.com/articles/accurate-reports',
                'description' => 'توضیحات کامل درباره اهمیت ارسال گزارشات دقیق.',
                'sort_order' => 9,
            ],
            [
                'title' => 'پی‌دی‌اف: راهنمای گام به گام برای انجام ماموریت‌ها',
                'content_type' => 'pdf',
                'content_url' => 'https://example.com/pdfs/mission-complete-guide.pdf',
                'description' => 'راهنمای جامع و گام به گام برای انجام ماموریت‌ها.',
                'sort_order' => 10,
            ],
        ];

        foreach ($contents as $index => $contentData) {
            $content = Content::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'title' => $contentData['title'],
                ],
                array_merge($contentData, [
                    'tenant_id' => $tenant->id,
                ])
            );

            Log::info('Content created/updated', [
                'id' => $content->id,
                'index' => $index + 1,
                'type' => $content->content_type
            ]);
        }

        Log::info('ContentSeeder completed', ['count' => count($contents)]);
    }
}
