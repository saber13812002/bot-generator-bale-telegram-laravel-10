<?php

namespace Database\Seeders;

use App\Models\Prompt;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('Starting PromptSeeder');

        $tenant = Tenant::first();
        if (!$tenant) {
            Log::warning('No tenant found. Please run TenantSeeder first.');
            return;
        }

        $prompts = [
            [
                'content' => 'برای انجام این ماموریت، ابتدا وارد سیستم شوید و اطلاعات خود را تکمیل کنید. سپس مراحل زیر را به دقت دنبال کنید.',
            ],
            [
                'content' => 'لطفاً دستورالعمل‌های زیر را دنبال کنید تا مراحل بعدی را تکمیل کنید. در صورت بروز هرگونه مشکل، با تیم پشتیبانی تماس بگیرید.',
            ],
            [
                'content' => 'برای شروع، به لینک زیر بروید و فرم را پر کنید. تمامی فیلدهای اجباری را با دقت تکمیل کنید.',
            ],
            [
                'content' => 'آیا آماده‌اید که ماموریت خود را شروع کنید؟ ابتدا مراحل زیر را انجام دهید و سپس نتیجه را به ما اطلاع دهید.',
            ],
            [
                'content' => 'لطفاً تمامی اطلاعات مورد نیاز را وارد کنید و منتظر تایید باشید. پس از تایید، می‌توانید به مرحله بعدی بروید.',
            ],
            [
                'content' => 'تمامی ماموریت‌ها باید تا تاریخ مشخص شده تکمیل شوند. لطفاً زمان‌بندی خود را مدیریت کنید.',
            ],
            [
                'content' => 'برای تکمیل ماموریت، فایل‌های مربوطه را بارگذاری کنید. مطمئن شوید که تمامی فایل‌ها در فرمت صحیح هستند.',
            ],
            [
                'content' => 'لطفاً ویدیوهای آموزشی مربوط به این ماموریت را مشاهده کنید. این ویدیوها به شما کمک می‌کنند تا ماموریت را به درستی انجام دهید.',
            ],
            [
                'content' => 'تمام جزئیات را وارد کرده و برای تایید به تیم ارسال کنید. پس از بررسی، نتیجه به شما اطلاع داده خواهد شد.',
            ],
            [
                'content' => 'آیا می‌خواهید ماموریت جدیدی را آغاز کنید؟ اگر بله، ابتدا فرم مربوطه را پر کنید و سپس منتظر تایید بمانید.',
            ],
        ];

        foreach ($prompts as $index => $promptData) {
            $prompt = Prompt::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'content' => $promptData['content'],
                ],
                [
                    'tenant_id' => $tenant->id,
                    'content' => $promptData['content'],
                    'task_id' => null,
                    'mission_id' => null,
                ]
            );

            Log::info('Prompt created/updated', [
                'id' => $prompt->id,
                'index' => $index + 1
            ]);
        }

        Log::info('PromptSeeder completed', ['count' => count($prompts)]);
    }
}
