<?php

namespace Database\Seeders;

use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class BotCreationWizardStepsSeeder extends Seeder
{
    /**
     * Define wizard_steps for endpoints that require extra configuration.
     * This seeder also creates missing endpoint records if they don't exist.
     */
    public function run(): void
    {
        // ===== Presenter Bot =====
        $this->updateWizardStepsOrCreate('presenter-bot', [
            'name' => 'Presenter Bot',
            'route' => 'api/webhook-presenter-bot',
            'description' => 'ربات ارائه محتوا به ترتیب - متن، عکس، ویدیو',
        ], [
            [
                'id' => 'presenter_content',
                'type' => 'collection',
                'label_fa' => 'محتوای ربات پرزنتر',
                'label_en' => 'Presenter Bot Content',
                'required' => false,
                'order' => 4,
                'accepts' => ['text', 'photo', 'video', 'voice', 'audio', 'document'],
                'help_fa' => 'محتوایی که ربات به ترتیب نمایش می‌دهد',
                'help_en' => 'Content items the bot will display in order',
            ],
        ]);

        // ===== Rating Bot =====
        $this->updateWizardStepsOrCreate('rating-bot', [
            'name' => 'Rating Bot',
            'route' => 'api/webhook-rating-bot',
            'description' => 'ربات امتیازدهی و نظر سنجی',
        ], [
            [
                'id' => 'rating_content',
                'type' => 'collection',
                'label_fa' => 'عبارت‌های نظر سنجی',
                'label_en' => 'Rating Items',
                'required' => false,
                'order' => 4,
                'accepts' => ['text', 'photo', 'video'],
                'help_fa' => 'عبارت‌هایی که کاربران به آنها امتیاز می‌دهند',
                'help_en' => 'Items users will rate',
            ],
        ]);

        // ===== Psychology Test Bot =====
        $this->updateWizardStepsOrCreate('psychology-test', [
            'name' => 'Psychology Test Bot',
            'route' => 'api/webhook-psychology-test',
            'description' => 'ربات تست روانشناسی با سوالات دسته‌بندی شده',
        ], [
            [
                'id' => 'psychology_questions',
                'type' => 'complex_wizard',
                'label_fa' => 'سوالات تست روانشناسی',
                'label_en' => 'Psychology Test Questions',
                'required' => false,
                'order' => 4,
                'wizard_type' => 'multi_step',
                'format_hint_fa' => 'سوال [دسته, وزن, جهت]',
                'format_hint_en' => 'Question [Category, Weight, Direction]',
                'wizard' => [
                    'steps' => [
                        [
                            'id' => 'questions',
                            'type' => 'bulk_text',
                            'label_fa' => 'سوالات را با فرمت وارد کنید',
                            'label_en' => 'Enter questions with format',
                            'format_hint_fa' => 'سوال [دسته, وزن, جهت]  مثال: آیا در جمع‌ها راحت هستید؟ [برون‌گرا, 1.0, 1]',
                            'format_hint_en' => 'Question [Category, Weight, Direction]',
                        ],
                        [
                            'id' => 'category_descriptions',
                            'type' => 'repeated_text',
                            'label_fa' => 'توضیحات دسته‌ها',
                            'label_en' => 'Category descriptions',
                        ],
                    ],
                ],
            ],
        ]);

        // ===== Content Submission Bot =====
        $this->updateWizardStepsOrCreate('content-submission', [
            'name' => 'محتوای متنی / عکس / فیلم',
            'route' => 'api/webhook-content-submission',
            'description' => 'ربات دریافت محتوا (متن/عکس/فیلم)، تایید در گروه با ریپلای «۱»، انتشار در کانال',
        ], [
            [
                'id' => 'content_channel_confirm',
                'type' => 'confirm',
                'label_fa' => 'آیا در کانال ادمین عضو هستید؟',
                'label_en' => 'Are you a member of the admin channel?',
                'required' => true,
                'order' => 4,
            ],
            [
                'id' => 'content_channel_id',
                'type' => 'forward',
                'label_fa' => 'شناسه کانال انتشار',
                'label_en' => 'Channel ID',
                'required' => true,
                'order' => 5,
                'help_fa' => 'یک پیام از کانال فوروارد کنید یا شناسه کانال را وارد کنید',
                'help_en' => 'Forward a message from the channel or enter the channel ID',
            ],
            [
                'id' => 'content_need_approval',
                'type' => 'yes_no',
                'label_fa' => 'آیا نیاز به تایید گروه دارید؟',
                'label_en' => 'Need approval group?',
                'required' => true,
                'order' => 6,
            ],
            [
                'id' => 'content_group_id',
                'type' => 'forward',
                'label_fa' => 'شناسه گروه تایید',
                'label_en' => 'Approval Group ID',
                'required' => false,
                'optional' => true,
                'order' => 7,
                'help_fa' => 'یک پیام از گروه تایید فوروارد کنید (اختیاری)',
                'help_en' => 'Forward a message from the approval group (optional)',
                'condition' => [
                    'field' => 'content_need_approval',
                    'operator' => '=',
                    'value' => 'yes',
                ],
            ],
            [
                'id' => 'content_required_approvals',
                'type' => 'number',
                'label_fa' => 'تعداد تاییدکنندگان مورد نیاز',
                'label_en' => 'Required Approvals',
                'required' => false,
                'optional' => true,
                'order' => 8,
                'help_fa' => '۱ یا ۲ نفر',
                'help_en' => '1 or 2',
                'condition' => [
                    'field' => 'content_need_approval',
                    'operator' => '=',
                    'value' => 'yes',
                ],
            ],
        ]);

        // ===== Book Library =====
        $this->updateWizardStepsOrCreate('book-library', [
            'name' => 'Smart Book Library',
            'route' => 'api/webhook-book-library',
            'description' => 'AI Book Coach - discover books, audio summaries, plan upgrades',
            'supports_multiple_languages' => true,
        ], [
            [
                'id' => 'reader_token',
                'type' => 'text',
                'label_fa' => 'توکن ربات کتابخوان (اختیاری)',
                'label_en' => 'Reader Bot Token (optional)',
                'required' => false,
                'order' => 4,
                'help_fa' => 'اگر ربات کتابخوان جداگانه دارید، توکن آن را وارد کنید',
                'help_en' => 'If you have a separate reader bot, enter its token',
            ],
        ]);

        // ===== Weather Bot =====
        $this->updateWizardStepsOrCreate('weather-bot', [
            'name' => 'Weather Bot',
            'route' => 'api/webhook-weather',
            'description' => 'پیش‌بینی آب و هوا با هشدارهای خودکار',
            'requires_language' => true,
        ], [
            [
                'id' => 'language',
                'type' => 'select',
                'label_fa' => 'زبان ربات',
                'label_en' => 'Bot Language',
                'required' => true,
                'order' => 2,
                'options' => [
                    ['value' => 'fa', 'label_fa' => 'فارسی', 'label_en' => 'Persian'],
                    ['value' => 'en', 'label_fa' => 'انگلیسی', 'label_en' => 'English'],
                ],
            ],
        ]);

        // ===== Quran Bot =====
        $this->updateWizardStepsOrCreate('quran-bot', [
            'name' => 'Quran Bot',
            'route' => 'api/webhook-quran-word',
            'description' => 'قرآن کریم با امکانات جستجو، حفظ و ترجمه',
            'requires_language' => true,
            'supports_multiple_languages' => true,
        ], []);

        // ===== Hadith Bot =====
        $this->updateWizardStepsOrCreate('hadith-bot', [
            'name' => 'Hadith Bot',
            'route' => 'api/webhook-hadith',
            'description' => 'جستجو در کتب حدیث شیعه',
        ], []);

        // ===== Nahj Bot =====
        $this->updateWizardStepsOrCreate('nahj-bot', [
            'name' => 'Nahj al-Balagha Bot',
            'route' => 'api/webhook-nahj',
            'description' => 'جستجو در نهج البلاغه',
        ], []);

        // ===== Prayer Bot =====
        $this->updateWizardStepsOrCreate('prayer-bot', [
            'name' => 'Prayer Qadha Bot',
            'route' => 'api/webhook-prayer-bot',
            'description' => 'ثبت و پیگیری نماز قضا',
            'supports_multiple_languages' => true,
        ], []);

        // ===== Mission Bot =====
        $this->updateWizardStepsOrCreate('mission-bot', [
            'name' => 'Mission Bot',
            'route' => 'api/webhook-mission-bot',
            'description' => 'مدیریت ماموریت‌ها',
        ], []);

        // ===== Personnel Registration =====
        $this->updateWizardStepsOrCreate('personnel-registration', [
            'name' => 'Personnel Registration Bot',
            'route' => 'api/webhook-personnel-registration',
            'description' => 'ثبت‌نام پرسنل',
        ], []);

        // ===== Book Library Reader =====
        $this->updateWizardStepsOrCreate('book-library-reader', [
            'name' => 'Book Library Reader',
            'route' => 'api/webhook-book-library-reader',
            'description' => 'Dedicated reader bot for book audio/PDF delivery',
        ], []);

        // ===== Poem Bot =====
        $this->updateWizardStepsOrCreate('poem-bot', [
            'name' => 'Poem Bot',
            'route' => 'api/webhook-poem-bot',
            'description' => 'ربات شعر',
        ], []);

        Log::info('✅ Bot Creation Wizard Steps seeded successfully');
    }

    /**
     * Update wizard_steps for an endpoint. If the endpoint doesn't exist, create it first.
     */
    private function updateWizardStepsOrCreate(string $endpointId, array $endpointDefaults, array $extraSteps): void
    {
        $endpoint = WebhookEndpoint::where('endpoint_id', $endpointId)->first();

        if (!$endpoint) {
            // Create the endpoint record
            $endpoint = WebhookEndpoint::create(array_merge([
                'endpoint_id' => $endpointId,
                'name' => $endpointDefaults['name'] ?? $endpointId,
                'route' => $endpointDefaults['route'] ?? 'api/webhook-' . $endpointId,
                'description' => $endpointDefaults['description'] ?? '',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => $endpointDefaults['requires_language'] ?? false,
                'supports_multiple_languages' => $endpointDefaults['supports_multiple_languages'] ?? false,
                'is_active' => true,
            ]));

            $this->command->info("🆕 Endpoint created: {$endpointId}");
        }

        if (!empty($extraSteps)) {
            $endpoint->wizard_steps = $extraSteps;
            $endpoint->save();
            $this->command->info("✅ Wizard steps updated for: {$endpointId}");
        } else {
            // Ensure wizard_steps is null/empty for endpoints without extra steps
            if ($endpoint->wizard_steps !== null) {
                $endpoint->wizard_steps = null;
                $endpoint->save();
            }
            $this->command->info("ℹ️  No wizard steps needed for: {$endpointId}");
        }
    }
}
